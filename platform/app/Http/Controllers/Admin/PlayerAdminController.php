<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AdminLog;
use App\Models\Deposit;
use App\Models\FinanceEntry;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AdminLogger;
use App\Services\FinanceLedgerService;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerAdminController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->get('q', ''));

        $players = User::query()
            ->with('affiliate')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('email', 'ilike', "%{$q}%")
                        ->orWhere('name', 'ilike', "%{$q}%")
                        ->orWhere('cpf', 'ilike', "%{$q}%")
                        ->orWhere('cidade', 'ilike', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $stats = $this->statsForUserIds($players->getCollection()->pluck('id')->all());

        return view('admin.players.index', compact('players', 'q', 'stats'));
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->get('q', ''));

        $filename = 'jogadores-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
            fputcsv($out, ['Nome', 'Email', 'CPF', 'Sexo', 'UF', 'Cidade'], ';');

            User::query()
                ->when($q !== '', function ($query) use ($q) {
                    $query->where(function ($inner) use ($q) {
                        $inner->where('email', 'ilike', "%{$q}%")
                            ->orWhere('name', 'ilike', "%{$q}%")
                            ->orWhere('cpf', 'ilike', "%{$q}%")
                            ->orWhere('cidade', 'ilike', "%{$q}%");
                    });
                })
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($out) {
                    foreach ($rows as $user) {
                        fputcsv($out, [
                            $user->name,
                            $user->email,
                            $user->cpf ?? '',
                            $user->sexo ?? '',
                            $user->estado ?? '',
                            $user->cidade ?? '',
                        ], ';');
                    }
                });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function show(User $user): View
    {
        $user->load('affiliate');

        $history = AdminLog::query()
            ->with('admin')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->where('action', 'player.balance_adjusted')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $stats = $this->statsForUserIds([$user->id])[$user->id] ?? $this->emptyStats();

        return view('admin.players.show', compact('user', 'history', 'stats'));
    }

    public function toggleBlock(Request $request, User $user, AdminLogger $logger): RedirectResponse
    {
        if ($user->is_admin && $user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Você não pode bloquear a si mesmo.']);
        }

        $before = ['is_blocked' => $user->is_blocked];
        $user->is_blocked = ! $user->is_blocked;
        $user->save();

        $logger->record(
            $request->user(),
            $user->is_blocked ? 'player.blocked' : 'player.unblocked',
            $user,
            $before,
            ['is_blocked' => $user->is_blocked]
        );

        return back()->with('status', $user->is_blocked ? 'Jogador bloqueado.' : 'Jogador desbloqueado.');
    }

    public function toggleKyc(Request $request, User $user, AdminLogger $logger): RedirectResponse
    {
        $before = ['kyc_verified' => $user->kyc_verified];
        $user->kyc_verified = ! $user->kyc_verified;
        $user->save();

        $logger->record($request->user(), 'player.kyc_toggled', $user, $before, ['kyc_verified' => $user->kyc_verified]);

        return back()->with('status', $user->kyc_verified ? 'KYC marcado como verificado.' : 'KYC revogado.');
    }

    public function clearFraudFlag(Request $request, User $user, AdminLogger $logger): RedirectResponse
    {
        $before = ['fraud_flag' => $user->fraud_flag, 'fraud_note' => $user->fraud_note];
        $user->fraud_flag = false;
        $user->fraud_note = trim(($user->fraud_note ? $user->fraud_note."\n" : '').now()->format('d/m/Y H:i').' · flag limpa por '.$request->user()->email);
        $user->save();

        $logger->record($request->user(), 'player.fraud_cleared', $user, $before, ['fraud_flag' => false]);

        return back()->with('status', 'Flag de suspeito removida.');
    }

    public function adjustBalance(
        Request $request,
        User $user,
        WalletService $wallet,
        FinanceLedgerService $ledger,
        AdminLogger $logger,
    ): RedirectResponse {
        $data = $request->validate([
            'balance' => ['required', 'numeric', 'min:0'],
            'bonus_balance' => ['required', 'numeric', 'min:0'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $before = [
            'balance' => (float) $user->balance,
            'bonus_balance' => (float) $user->bonus_balance,
        ];

        $wallet->setBalances($user, (float) $data['balance'], (float) $data['bonus_balance']);
        $user->refresh();

        $realDelta = round((float) $user->balance - $before['balance'], 2);
        $bonusDelta = round((float) $user->bonus_balance - $before['bonus_balance'], 2);

        if ($realDelta !== 0.0) {
            $ledger->record(
                FinanceEntry::TYPE_MANUAL_ADJUSTMENT,
                $realDelta > 0 ? FinanceEntry::DIR_OUT : FinanceEntry::DIR_IN,
                abs($realDelta),
                $user,
                $request->user(),
                $data['note'].' (saldo real)'
            );
        }

        $logger->record(
            $request->user(),
            'player.balance_adjusted',
            $user,
            $before,
            [
                'balance' => (float) $user->balance,
                'bonus_balance' => (float) $user->bonus_balance,
                'real_delta' => $realDelta,
                'bonus_delta' => $bonusDelta,
                'note' => $data['note'],
            ]
        );

        $status = 'Saldo atualizado.';
        if ($bonusDelta !== 0.0 && $realDelta === 0.0) {
            $status = 'Bônus atualizado (não altera o caixa da casa).';
        } elseif ($bonusDelta !== 0.0) {
            $status = 'Saldo real e bônus atualizados. Só o saldo real entrou no financeiro.';
        }

        return back()->with('status', $status);
    }

    public function makeAffiliate(Request $request, User $user, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:15'],
            'pix_key' => ['nullable', 'string', 'max:120'],
        ]);

        $existing = Affiliate::query()->where('user_id', $user->id)->first();

        $affiliate = Affiliate::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'commission_percent' => round((float) $data['commission_percent'], 2),
                'pix_key' => filled($data['pix_key'] ?? null) ? trim($data['pix_key']) : ($existing?->pix_key),
                'referral_code' => $existing?->referral_code ?: Affiliate::generateReferralCode(),
                'active' => true,
            ]
        );

        $logger->record($request->user(), 'player.made_affiliate', $user, null, $affiliate->toArray());

        return redirect()
            ->route('admin.affiliates.show', $affiliate)
            ->with('status', 'Jogador marcado como afiliado. Código: '.$affiliate->referral_code);
    }

    /**
     * @param  list<int|string>  $userIds
     * @return array<int, array{deposited: float, withdrawn: float, result_pct: ?float}>
     */
    protected function statsForUserIds(array $userIds): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $deposits = Deposit::query()
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('user_id', $userIds)
            ->where('status', Deposit::STATUS_PAID)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $withdrawals = Withdrawal::query()
            ->select('user_id', DB::raw('COALESCE(SUM(amount), 0) as total'))
            ->whereIn('user_id', $userIds)
            ->where(function ($q) {
                $q->whereNull('source')->orWhere('source', Withdrawal::SOURCE_PLAYER);
            })
            ->whereIn('status', [Withdrawal::STATUS_PAID, Withdrawal::STATUS_APPROVED])
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $balances = User::query()
            ->whereIn('id', $userIds)
            ->pluck('balance', 'id');

        $out = [];
        foreach ($userIds as $id) {
            $deposited = round((float) ($deposits[$id] ?? 0), 2);
            $withdrawn = round((float) ($withdrawals[$id] ?? 0), 2);
            $balance = round((float) ($balances[$id] ?? 0), 2);
            $resultPct = null;
            if ($deposited > 0) {
                $resultPct = round((($withdrawn + $balance) - $deposited) / $deposited * 100, 2);
            }
            $out[$id] = [
                'deposited' => $deposited,
                'withdrawn' => $withdrawn,
                'result_pct' => $resultPct,
            ];
        }

        return $out;
    }

    /**
     * @return array{deposited: float, withdrawn: float, result_pct: null}
     */
    protected function emptyStats(): array
    {
        return [
            'deposited' => 0.0,
            'withdrawn' => 0.0,
            'result_pct' => null,
        ];
    }
}
