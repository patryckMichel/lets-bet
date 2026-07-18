<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\AffiliateFraudService;
use App\Services\OpsMetricsService;
use App\Services\WageringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WithdrawalController extends Controller
{
    public function create(WageringService $wagering): View
    {
        /** @var User $user */
        $user = auth()->user();
        $minWithdrawal = round((float) Setting::getValue('withdrawal_min', 200), 2);
        $wageringStatus = $wagering->status($user);

        return view('withdrawals.create', [
            'realBalance' => round((float) $user->balance, 2),
            'bonusBalance' => round((float) $user->bonus_balance, 2),
            'minWithdrawal' => $minWithdrawal,
            'wagering' => $wageringStatus,
            'pending' => Withdrawal::query()
                ->where('user_id', $user->id)
                ->where('status', Withdrawal::STATUS_PENDING)
                ->latest()
                ->get(),
        ]);
    }

    public function store(
        Request $request,
        OpsMetricsService $ops,
        WageringService $wagering,
        AffiliateFraudService $fraud,
    ): RedirectResponse {
        $minWithdrawal = round((float) Setting::getValue('withdrawal_min', 200), 2);
        $kycThreshold = round((float) Setting::getValue('kyc_withdraw_threshold', 500), 2);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:'.$minWithdrawal, 'max:50000'],
            'pix_key' => ['required', 'string', 'max:120'],
        ], [
            'amount.min' => 'Saque mínimo R$ '.number_format($minWithdrawal, 2, ',', '.').'.',
        ]);

        $amount = round((float) $data['amount'], 2);
        $pixKey = trim($data['pix_key']);
        $withdrawalId = null;

        try {
            DB::transaction(function () use ($request, $amount, $pixKey, $wagering, $fraud, $kycThreshold, &$withdrawalId) {
                /** @var User $user */
                $user = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

                if (! $wagering->isMet($user)) {
                    $left = $wagering->remaining($user);
                    throw new \RuntimeException(
                        'Complete o rollover antes de sacar. Faltam R$ '.number_format($left, 2, ',', '.').' em apostas.'
                    );
                }

                if ($user->fraud_flag) {
                    throw new \RuntimeException('Saque bloqueado para análise de segurança. Contate o suporte.');
                }

                if ($amount >= $kycThreshold && ! $user->kyc_verified) {
                    throw new \RuntimeException(
                        'Saques a partir de R$ '.number_format($kycThreshold, 2, ',', '.').' exigem verificação KYC. Contate o suporte.'
                    );
                }

                $fraud->assertWithdrawalPixNotAffiliate($user, $pixKey);

                $realBalance = round((float) $user->balance, 2);

                if ($realBalance < $amount) {
                    throw new \RuntimeException('Saldo real insuficiente. O bônus não pode ser sacado.');
                }

                $user->balance = round($realBalance - $amount, 2);
                $user->save();

                $withdrawal = Withdrawal::query()->create([
                    'user_id' => $user->id,
                    'source' => Withdrawal::SOURCE_PLAYER,
                    'amount' => $amount,
                    'pix_key' => $pixKey,
                    'status' => Withdrawal::STATUS_PENDING,
                ]);

                $withdrawalId = $withdrawal->id;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        $ops->recordWithdrawalRequested((int) $request->user()->id, $amount, $withdrawalId);

        return redirect()
            ->route('withdrawals.create')
            ->with('status', 'Solicitação de saque enviada. Aguarde análise.');
    }
}
