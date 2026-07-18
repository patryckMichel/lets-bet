<?php

namespace App\Http\Controllers;

use App\Models\BonusCode;
use App\Models\Deposit;
use App\Models\Setting;
use App\Models\User;
use App\Services\AsaasPixService;
use App\Services\DepositSettlementService;
use App\Services\MercadoPagoPixService;
use App\Services\PendingDepositCleanupService;
use App\Services\PixConfigService;
use App\Services\VelocityService;
use App\Support\Cpf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DepositController extends Controller
{
    public function create(): View
    {
        $user = auth()->user();

        return view('deposits.create', [
            'presets' => [10, 20, 50, 100, 200, 500],
            'min' => (float) Setting::getValue('deposit_min', 5),
            'max' => (float) Setting::getValue('deposit_max', 5000),
            'balance' => $user->total_balance,
            'needsCpf' => ! Cpf::isValid((string) ($user->cpf ?? '')),
        ]);
    }

    public function store(
        Request $request,
        PixConfigService $pixConfig,
        MercadoPagoPixService $mp,
        AsaasPixService $asaas,
        VelocityService $velocity,
    ): RedirectResponse {
        $min = (float) Setting::getValue('deposit_min', 5);
        $max = (float) Setting::getValue('deposit_max', 5000);
        $user = $request->user();
        $needsCpf = ! Cpf::isValid((string) ($user->cpf ?? ''));
        $ip = $request->ip();

        try {
            $velocity->assertDepositAllowed($ip);
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $rules = [
            'amount' => ['required', 'numeric', "min:{$min}", "max:{$max}"],
            'bonus_code' => ['nullable', 'string', 'max:40'],
        ];
        if ($needsCpf || $pixConfig->provider() === 'asaas') {
            $rules['cpf'] = [$needsCpf ? 'required' : 'nullable', 'string', 'max:14'];
        }

        $data = $request->validate($rules, [
            'amount.required' => 'Informe o valor do depósito.',
            'amount.min' => "O valor mínimo é $ {$min}.",
            'amount.max' => "O valor máximo é $ {$max}.",
            'cpf.required' => 'Informe seu CPF para gerar o PIX.',
        ]);

        if (! empty($data['cpf']) || $needsCpf) {
            $cpf = Cpf::digits((string) ($data['cpf'] ?? ''));
            if (! Cpf::isValid($cpf)) {
                return back()->withInput()->withErrors(['cpf' => 'CPF inválido.']);
            }

            $taken = User::query()
                ->where('cpf', $cpf)
                ->where('id', '!=', $user->id)
                ->exists();

            if ($taken) {
                return back()->withInput()->withErrors(['cpf' => 'Este CPF já está cadastrado em outra conta.']);
            }

            if ($user->cpf !== $cpf) {
                $user->cpf = $cpf;
                $user->save();
            }
        }

        $bonusCode = null;
        if (! empty($data['bonus_code'])) {
            $bonusCode = BonusCode::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper(trim($data['bonus_code']))])
                ->first();

            if (! $bonusCode || ! $bonusCode->isUsable() || ! $bonusCode->isCodeKind()) {
                return back()->withInput()->withErrors(['bonus_code' => 'Código de bônus inválido ou expirado.']);
            }

            if ($bonusCode->hasBeenUsedBy((int) $user->id, 'once')) {
                return back()->withInput()->withErrors(['bonus_code' => 'Você já utilizou este código de bônus.']);
            }
        }

        $amount = round((float) $data['amount'], 2);
        $txid = strtoupper(Str::random(20));

        $deposit = Deposit::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'status' => Deposit::STATUS_PENDING,
            'txid' => $txid,
            'bonus_code' => $bonusCode?->code,
            'bonus_code_id' => $bonusCode?->id,
            'pix_copy' => '',
        ]);

        try {
            $pix = match ($pixConfig->provider()) {
                'asaas' => $asaas->createPixPayment($deposit, $user->fresh()),
                'static' => $mp->createPixPayment($deposit, $user->email),
                default => $mp->createPixPayment($deposit, $user->email),
            };
        } catch (\Throwable $e) {
            $deposit->delete();

            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $deposit->update([
            'mp_payment_id' => $pix['mp_payment_id'] ?: null,
            'pix_copy' => $pix['pix_copy'],
            'qr_code_base64' => $pix['qr_code_base64'] ?: null,
        ]);

        $velocity->hitDeposit($ip);

        return redirect()->route('deposits.show', $deposit);
    }

    public function show(
        Deposit $deposit,
        PixConfigService $pixConfig,
        PendingDepositCleanupService $cleanup,
    ): View {
        abort_unless($deposit->user_id === auth()->id(), 403);

        if ($deposit->isPending()) {
            $cleanup->expireOne($deposit);
            $deposit->refresh();
        }

        $ttl = $cleanup->ttlSeconds();
        $expiresAt = $deposit->isPending()
            ? $deposit->created_at->copy()->addSeconds($ttl)
            : null;
        $secondsLeft = $expiresAt
            ? max(0, $expiresAt->getTimestamp() - now()->getTimestamp())
            : 0;

        $qrUrl = null;
        if ($deposit->isPending() && $deposit->qr_code_base64) {
            $qrUrl = 'data:image/png;base64,'.$deposit->qr_code_base64;
        } elseif ($deposit->isPending() && $deposit->pix_copy) {
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=260x260&margin=12&data='
                .urlencode($deposit->pix_copy);
        }

        return view('deposits.show', [
            'deposit' => $deposit,
            'qrUrl' => $qrUrl,
            'balance' => auth()->user()->total_balance,
            'allowLocalConfirm' => app()->environment('local') && ! $pixConfig->isProviderConfigured(),
            'canVerifyPayment' => $deposit->isPending()
                && $pixConfig->isProviderConfigured()
                && filled($deposit->mp_payment_id),
            'ttlSeconds' => $ttl,
            'secondsLeft' => (int) $secondsLeft,
            'expiresAt' => $expiresAt,
        ]);
    }

    public function verify(
        Deposit $deposit,
        PixConfigService $pixConfig,
        MercadoPagoPixService $mp,
        AsaasPixService $asaas,
        DepositSettlementService $settlement,
    ): RedirectResponse {
        abort_unless($deposit->user_id === auth()->id(), 403);
        abort_unless($pixConfig->isProviderConfigured(), 403);
        abort_unless($deposit->isPending(), 422);
        abort_unless(filled($deposit->mp_payment_id), 422);

        if ($deposit->isPaid()) {
            return redirect()
                ->route('deposits.show', $deposit)
                ->with('status', 'Este depósito já foi creditado.');
        }

        try {
            if ($pixConfig->provider() === 'asaas') {
                $payment = $asaas->fetchPayment((string) $deposit->mp_payment_id);
                $paid = $asaas->isPaidStatus((string) ($payment['status'] ?? ''));
                $status = (string) ($payment['status'] ?? '');
            } else {
                $payment = $mp->fetchPayment((string) $deposit->mp_payment_id);
                $status = (string) ($payment['status'] ?? '');
                $paid = $status === 'approved';
            }
        } catch (\Throwable $e) {
            return redirect()
                ->route('deposits.show', $deposit)
                ->withErrors(['verify' => 'Não foi possível consultar o pagamento. Tente novamente em instantes.']);
        }

        if (! $paid) {
            $messages = [
                'pending' => 'Pagamento ainda pendente. Aguarde a confirmação do PIX.',
                'PENDING' => 'Pagamento ainda pendente. Aguarde a confirmação do PIX.',
                'in_process' => 'Pagamento em processamento. Aguarde alguns instantes.',
                'rejected' => 'Pagamento recusado ou cancelado.',
                'OVERDUE' => 'Cobrança vencida. Gere um novo PIX.',
            ];

            return redirect()
                ->route('deposits.show', $deposit)
                ->withErrors(['verify' => $messages[$status] ?? 'Pagamento ainda não confirmado.']);
        }

        $netValue = null;
        if ($pixConfig->provider() === 'asaas' && isset($payment['netValue'])) {
            $netValue = (float) $payment['netValue'];
        }

        $settlement->settlePaid($deposit, $netValue);

        return redirect()
            ->route('deposits.show', $deposit->fresh())
            ->with('status', 'Pagamento confirmado! Saldo creditado.');
    }

    public function confirm(Deposit $deposit, DepositSettlementService $settlement, PixConfigService $pixConfig): RedirectResponse
    {
        abort_unless($deposit->user_id === auth()->id(), 403);
        abort_unless(app()->environment('local') && ! $pixConfig->isProviderConfigured(), 403);

        if ($deposit->isPaid()) {
            return redirect()
                ->route('deposits.show', $deposit)
                ->with('status', 'Este depósito já foi creditado.');
        }

        abort_unless($deposit->isPending(), 422);

        $settlement->settlePaid($deposit);

        return redirect()
            ->route('deposits.show', $deposit->fresh())
            ->with('status', 'Saldo adicionado com sucesso! (modo local)');
    }
}
