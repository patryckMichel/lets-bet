<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\BonusCode;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AffiliateCommissionService
{
    public function __construct(
        private PlayerBonusService $playerBonus,
    ) {}

    public function createFromDeposit(Deposit $deposit): ?AffiliateCommission
    {
        $deposit->loadMissing('user');
        $user = $deposit->user;
        if (! $user) {
            return null;
        }

        $affiliate = null;
        if ($user->affiliate_id) {
            $affiliate = Affiliate::query()
                ->whereKey($user->affiliate_id)
                ->where('active', true)
                ->first();
        }

        // Fallback legado: depósito com código de afiliado (só se jogador ainda sem carteira).
        if (! $affiliate && $deposit->bonus_code_id) {
            $code = BonusCode::query()->with('affiliate')->find($deposit->bonus_code_id);
            if ($code?->affiliate?->active) {
                $affiliate = $code->affiliate;
                if (! $user->affiliate_id) {
                    $user->affiliate_id = $affiliate->id;
                    $user->save();
                }
            }
        }

        if (! $affiliate) {
            return null;
        }

        $base = $this->commissionBase($deposit);
        $percent = (float) $affiliate->commission_percent;
        $commission = round($base * ($percent / 100), 2);

        if ($commission <= 0) {
            return null;
        }

        return AffiliateCommission::query()->firstOrCreate(
            ['deposit_id' => $deposit->id],
            [
                'affiliate_id' => $affiliate->id,
                'base_amount' => $base,
                'commission_amount' => $commission,
                'status' => AffiliateCommission::STATUS_OPEN,
            ]
        );
    }

    public function applyPlayerBonus(Deposit $deposit, User $user): void
    {
        $this->playerBonus->applyDepositBonuses($deposit, $user);
    }

    public function resolveAffiliateByCode(string $rawCode): ?Affiliate
    {
        $code = strtoupper(trim($rawCode));
        if ($code === '') {
            return null;
        }

        $affiliate = Affiliate::query()
            ->whereRaw('UPPER(referral_code) = ?', [$code])
            ->where('active', true)
            ->first();

        if ($affiliate) {
            return $affiliate;
        }

        $bonus = BonusCode::query()
            ->with('affiliate')
            ->whereRaw('UPPER(code) = ?', [$code])
            ->where('active', true)
            ->first();

        if ($bonus?->affiliate?->active) {
            return $bonus->affiliate;
        }

        return null;
    }

    /**
     * Preview commissions for open deposits in period (creates missing open rows).
     *
     * @return array{items: Collection<int, AffiliateCommission>, total: float, count: int, deposits_sum: float}
     */
    public function previewPeriod(Affiliate $affiliate, CarbonInterface $from, CarbonInterface $to): array
    {
        $fromStart = $from->copy()->startOfDay();
        $toEnd = $to->copy()->endOfDay();

        $playerIds = User::query()
            ->where('affiliate_id', $affiliate->id)
            ->pluck('id');

        if ($playerIds->isEmpty()) {
            return [
                'items' => collect(),
                'total' => 0.0,
                'count' => 0,
                'deposits_sum' => 0.0,
            ];
        }

        $deposits = Deposit::query()
            ->whereIn('user_id', $playerIds)
            ->where('status', Deposit::STATUS_PAID)
            ->whereBetween('paid_at', [$fromStart, $toEnd])
            ->orderBy('paid_at')
            ->get();

        $percent = (float) $affiliate->commission_percent;
        $items = collect();

        foreach ($deposits as $deposit) {
            $existing = AffiliateCommission::query()
                ->where('deposit_id', $deposit->id)
                ->first();

            if ($existing) {
                if ($existing->status === AffiliateCommission::STATUS_OPEN
                    && (int) $existing->affiliate_id === (int) $affiliate->id) {
                    $items->push($existing);
                }
                continue;
            }

            $base = $this->commissionBase($deposit);
            $amount = round($base * ($percent / 100), 2);
            if ($amount <= 0) {
                continue;
            }

            $created = AffiliateCommission::query()->create([
                'affiliate_id' => $affiliate->id,
                'deposit_id' => $deposit->id,
                'base_amount' => $base,
                'commission_amount' => $amount,
                'status' => AffiliateCommission::STATUS_OPEN,
            ]);
            $items->push($created);
        }

        $items = new EloquentCollection($items->all());
        $items->load(['deposit.user']);

        return [
            'items' => $items,
            'total' => round((float) $items->sum('commission_amount'), 2),
            'count' => $items->count(),
            'deposits_sum' => round((float) $items->sum('base_amount'), 2),
        ];
    }

    /**
     * Confirm preview: reserve commissions and create withdrawal in /admin/saques.
     */
    public function confirmPeriod(
        Affiliate $affiliate,
        CarbonInterface $from,
        CarbonInterface $to,
        ?User $admin = null,
    ): Withdrawal {
        if (! $affiliate->hasPixKey()) {
            throw new RuntimeException('Cadastre a chave PIX de recebimento do afiliado antes de confirmar.');
        }

        if (! $affiliate->active) {
            throw new RuntimeException('Afiliado inativo.');
        }

        return DB::transaction(function () use ($affiliate, $from, $to, $admin) {
            $preview = $this->previewPeriod($affiliate, $from, $to);
            if ($preview['count'] === 0 || $preview['total'] <= 0) {
                throw new RuntimeException('Não há depósitos elegíveis para comissão neste período.');
            }

            $ids = $preview['items']->pluck('id')->all();

            $locked = AffiliateCommission::query()
                ->whereIn('id', $ids)
                ->where('status', AffiliateCommission::STATUS_OPEN)
                ->lockForUpdate()
                ->get();

            if ($locked->count() !== count($ids)) {
                throw new RuntimeException('Alguns depósitos já foram reservados. Recalcule o período.');
            }

            $total = round((float) $locked->sum('commission_amount'), 2);

            $note = sprintf(
                'Comissão afiliado #%d · %s a %s · %d depósito(s)',
                $affiliate->id,
                $from->format('d/m/Y'),
                $to->format('d/m/Y'),
                $locked->count()
            );

            $withdrawal = Withdrawal::query()->create([
                'user_id' => $affiliate->user_id,
                'source' => Withdrawal::SOURCE_AFFILIATE,
                'affiliate_id' => $affiliate->id,
                'amount' => $total,
                'pix_key' => trim((string) $affiliate->pix_key),
                'status' => Withdrawal::STATUS_PENDING,
                'admin_note' => $note.($admin ? ' · por '.$admin->email : ''),
            ]);

            AffiliateCommission::query()
                ->whereIn('id', $locked->pluck('id'))
                ->update([
                    'status' => AffiliateCommission::STATUS_RESERVED,
                    'withdrawal_id' => $withdrawal->id,
                ]);

            return $withdrawal->fresh();
        });
    }

    public function releaseCommissionsForWithdrawal(Withdrawal $withdrawal): void
    {
        if (! $withdrawal->isAffiliateCommission()) {
            return;
        }

        AffiliateCommission::query()
            ->where('withdrawal_id', $withdrawal->id)
            ->where('status', AffiliateCommission::STATUS_RESERVED)
            ->update([
                'status' => AffiliateCommission::STATUS_OPEN,
                'withdrawal_id' => null,
            ]);
    }

    public function markCommissionsPaidForWithdrawal(Withdrawal $withdrawal): void
    {
        if (! $withdrawal->isAffiliateCommission()) {
            return;
        }

        AffiliateCommission::query()
            ->where('withdrawal_id', $withdrawal->id)
            ->where('status', AffiliateCommission::STATUS_RESERVED)
            ->update([
                'status' => AffiliateCommission::STATUS_PAID,
                'credited_at' => now(),
            ]);
    }

    /**
     * Legacy cron — disabled for the new manual settlement flow.
     */
    public function creditDueCommissions(): int
    {
        return 0;
    }

    protected function commissionBase(Deposit $deposit): float
    {
        $net = (float) ($deposit->net_amount ?? 0);
        if ($net > 0) {
            return round($net, 2);
        }

        $gross = (float) $deposit->amount;
        $fee = (float) ($deposit->fee_amount ?? 0);
        if ($fee > 0 && $fee < $gross) {
            return round($gross - $fee, 2);
        }

        return round($gross, 2);
    }
}
