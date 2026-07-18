<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\FinanceEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DepositSettlementService
{
    public function __construct(
        private WalletService $wallet,
        private FinanceLedgerService $ledger,
        private AffiliateCommissionService $affiliates,
        private GatewayFeeService $fees,
        private OpsMetricsService $ops,
        private WageringService $wagering,
        private PlayerBonusService $playerBonus,
    ) {}

    public function settlePaid(Deposit $deposit, ?float $gatewayNetValue = null): bool
    {
        return DB::transaction(function () use ($deposit, $gatewayNetValue) {
            $locked = Deposit::query()
                ->whereKey($deposit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                return false;
            }

            // Permite creditar se o PIX chegou depois do cancelamento local
            if (! $locked->isPending() && $locked->status !== Deposit::STATUS_EXPIRED) {
                return false;
            }

            $user = User::query()
                ->whereKey($locked->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            $gross = (float) $locked->amount;
            $resolved = $this->fees->resolveDepositFee($gross, $gatewayNetValue);
            $fee = $resolved['fee'];
            $net = $resolved['net'];

            $paidBefore = Deposit::query()
                ->where('user_id', $user->id)
                ->where('status', Deposit::STATUS_PAID)
                ->where('id', '!=', $locked->id)
                ->count();

            $this->wallet->credit($user, $gross);
            $this->wagering->addRequirement($user, $gross);

            $this->affiliates->applyPlayerBonus($locked, $user);

            // Bônus cadastro com afiliado: só no 1º depósito pago.
            if ($paidBefore === 0 && $user->affiliate_id) {
                $this->playerBonus->applyAffiliateSignupBonus($user->fresh());
            }

            $locked->status = Deposit::STATUS_PAID;
            $locked->paid_at = now();
            $locked->fee_amount = $fee;
            $locked->net_amount = $net;
            $locked->save();

            $this->ledger->record(
                FinanceEntry::TYPE_DEPOSIT,
                FinanceEntry::DIR_IN,
                $gross,
                $locked,
                null,
                'Depósito PIX #'.$locked->id.' (bruto)'
            );

            if ($fee > 0) {
                $this->ledger->record(
                    FinanceEntry::TYPE_GATEWAY_FEE,
                    FinanceEntry::DIR_OUT,
                    $fee,
                    $locked,
                    null,
                    'Taxa gateway PIX #'.$locked->id.' (líquido $ '.number_format($net, 2, '.', ',').')'
                );
            }

            $this->affiliates->createFromDeposit($locked->fresh());

            $this->ops->recordDepositPaid((int) $locked->user_id, $gross, (int) $locked->id);

            return true;
        });
    }
}
