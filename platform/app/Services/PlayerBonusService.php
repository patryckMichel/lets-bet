<?php

namespace App\Services;

use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use App\Models\CrashBet;
use App\Models\Deposit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;

class PlayerBonusService
{
    public function __construct(private WalletService $wallet) {}

    public function applyDepositBonuses(Deposit $deposit, User $user): void
    {
        if ($deposit->bonus_code_id) {
            $this->applyTypedCode($deposit, $user);

            return;
        }

        $paidBefore = Deposit::query()
            ->where('user_id', $user->id)
            ->where('status', Deposit::STATUS_PAID)
            ->where('id', '!=', $deposit->id)
            ->count();

        if ($paidBefore === 0) {
            $this->applySystemCampaign(
                BonusCode::KIND_FIRST_DEPOSIT,
                $user,
                (float) $deposit->amount,
                $deposit,
                'once'
            );

            return;
        }

        $campaign = BonusCode::activeCampaign(BonusCode::KIND_RELOAD);
        if (! $campaign || ! $campaign->isUsable()) {
            return;
        }

        $days = (int) ($campaign->inactive_days ?? 0);
        if ($days < 1) {
            return;
        }

        $lastPaidAt = Deposit::query()
            ->where('user_id', $user->id)
            ->where('status', Deposit::STATUS_PAID)
            ->where('id', '!=', $deposit->id)
            ->orderByDesc('paid_at')
            ->value('paid_at');

        if (! $lastPaidAt) {
            return;
        }

        $inactiveSince = $lastPaidAt instanceof CarbonInterface
            ? $lastPaidAt
            : \Carbon\Carbon::parse($lastPaidAt);

        if ($inactiveSince->gt(now()->subDays($days))) {
            return;
        }

        $this->applySystemCampaign(
            BonusCode::KIND_RELOAD,
            $user,
            (float) $deposit->amount,
            $deposit,
            'dep:'.$deposit->id,
            $campaign
        );
    }

    public function applyNewPlayerBonus(User $user): void
    {
        $campaign = BonusCode::activeCampaign(BonusCode::KIND_NEW_PLAYER);
        if (! $campaign || ! $campaign->isUsable()) {
            return;
        }

        $bonus = max(0, round((float) $campaign->bonus_amount, 2));
        $this->grant($campaign, $user, $bonus, null, 'once');
    }

    public function applyAffiliateSignupBonus(User $user): void
    {
        if (! $user->affiliate_id) {
            return;
        }

        $campaign = BonusCode::activeCampaign(BonusCode::KIND_AFFILIATE_SIGNUP);
        if (! $campaign || ! $campaign->isUsable()) {
            return;
        }

        $dailyCap = (int) \App\Models\Setting::getValue('affiliate_signup_daily_cap', 50);
        if ($dailyCap > 0) {
            $todayCount = BonusCodeRedemption::query()
                ->where('bonus_code_id', $campaign->id)
                ->whereDate('created_at', today())
                ->count();
            if ($todayCount >= $dailyCap) {
                return;
            }
        }

        $bonus = max(0, round((float) $campaign->bonus_amount, 2));
        if ($bonus <= 0) {
            return;
        }

        $this->grant($campaign, $user, $bonus, null, 'once');
    }

    /**
     * Resgata código de valor fixo sem depósito (tela /bonus).
     *
     * @return array{bonus: float, code: string}
     */
    public function redeemFixedCode(User $user, string $rawCode): array
    {
        $normalized = strtoupper(trim($rawCode));

        $code = BonusCode::query()
            ->whereRaw('UPPER(code) = ?', [$normalized])
            ->lockForUpdate()
            ->first();

        if (! $code || ! $code->isUsable()) {
            throw new \RuntimeException('Código de bônus inválido ou expirado.');
        }

        if ($code->resolvedKind() !== BonusCode::KIND_FIXED) {
            throw new \RuntimeException('Este código não pode ser resgatado aqui. Use-o no depósito, se for um código de match.');
        }

        if ($code->hasBeenUsedBy((int) $user->id, 'once')) {
            throw new \RuntimeException('Você já utilizou este código de bônus.');
        }

        $bonus = max(0, round((float) $code->bonus_amount, 2));
        if ($bonus <= 0) {
            throw new \RuntimeException('Código de bônus inválido.');
        }

        if (! $this->grant($code, $user, $bonus, null, 'once')) {
            throw new \RuntimeException('Você já utilizou este código de bônus.');
        }

        return [
            'bonus' => $bonus,
            'code' => $code->code,
        ];
    }

    public function applyCashbackForDate(CarbonInterface $day): int
    {
        $campaign = BonusCode::activeCampaign(BonusCode::KIND_CASHBACK);
        if (! $campaign || ! $campaign->isUsable()) {
            return 0;
        }

        $percent = (float) ($campaign->match_percent ?? 0);
        if ($percent <= 0) {
            return 0;
        }

        $periodKey = 'cb:'.$day->toDateString();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();
        $credited = 0;

        $userIds = CrashBet::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [CrashBet::STATUS_LOST, CrashBet::STATUS_CASHED_OUT])
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $netLoss = $this->netLossForUser((int) $userId, $start, $end);
            if ($netLoss <= 0) {
                continue;
            }

            $bonus = $campaign->calculateCashback($netLoss);
            if ($bonus <= 0) {
                continue;
            }

            $user = User::query()->whereKey($userId)->first();
            if (! $user) {
                continue;
            }

            if ($this->grant($campaign, $user, $bonus, null, $periodKey)) {
                $credited++;
            }
        }

        return $credited;
    }

    protected function applyTypedCode(Deposit $deposit, User $user): void
    {
        $code = BonusCode::query()
            ->whereKey($deposit->bonus_code_id)
            ->lockForUpdate()
            ->first();

        if (! $code || ! $code->isUsable() || ! $code->isCodeKind()) {
            return;
        }

        if ($code->hasBeenUsedBy((int) $user->id, 'once')) {
            return;
        }

        $bonus = $code->calculateDepositReward((float) $deposit->amount);
        $this->grant($code, $user, $bonus, $deposit, 'once');
    }

    protected function applySystemCampaign(
        string $kind,
        User $user,
        float $depositAmount,
        Deposit $deposit,
        string $periodKey,
        ?BonusCode $campaign = null,
    ): void {
        $campaign ??= BonusCode::activeCampaign($kind);
        if (! $campaign || ! $campaign->isUsable()) {
            return;
        }

        if ($campaign->hasBeenUsedBy((int) $user->id, $periodKey)) {
            return;
        }

        $bonus = $campaign->calculateDepositReward($depositAmount);
        $this->grant($campaign, $user, $bonus, $deposit, $periodKey);
    }

    protected function grant(
        BonusCode $code,
        User $user,
        float $bonus,
        ?Deposit $deposit,
        string $periodKey,
    ): bool {
        $bonus = round($bonus, 2);

        try {
            BonusCodeRedemption::query()->create([
                'user_id' => $user->id,
                'bonus_code_id' => $code->id,
                'deposit_id' => $deposit?->id,
                'period_key' => $periodKey,
                'bonus_credited' => $bonus,
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($bonus > 0) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first() ?? $user;
            $this->wallet->creditBonus($locked, $bonus);
            if ($locked->id === $user->id) {
                $user->bonus_balance = $locked->bonus_balance;
                $user->wagering_required = $locked->wagering_required;
                $user->wagering_progress = $locked->wagering_progress;
            }
        }

        $code->increment('uses_count');

        return true;
    }

    protected function netLossForUser(int $userId, CarbonInterface $start, CarbonInterface $end): float
    {
        $bets = CrashBet::query()
            ->where('user_id', $userId)
            ->whereBetween('created_at', [$start, $end])
            ->whereIn('status', [CrashBet::STATUS_LOST, CrashBet::STATUS_CASHED_OUT])
            ->get(['amount', 'payout', 'status']);

        $staked = 0.0;
        $returned = 0.0;

        foreach ($bets as $bet) {
            $staked += (float) $bet->amount;
            if ($bet->status === CrashBet::STATUS_CASHED_OUT) {
                $returned += (float) ($bet->payout ?? 0);
            }
        }

        return max(0, round($staked - $returned, 2));
    }
}
