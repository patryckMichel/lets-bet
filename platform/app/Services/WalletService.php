<?php

namespace App\Services;

use App\Models\User;
use RuntimeException;

class WalletService
{
    public function __construct(private WageringService $wagering) {}

    public function available(User $user): float
    {
        return round((float) $user->balance + (float) $user->bonus_balance, 2);
    }

    /**
     * Debit stake: consume normal balance first, then bonus.
     *
     * @return array{from_balance: float, from_bonus: float}
     */
    public function debit(User $user, float $amount): array
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Valor inválido.');
        }

        if ($this->available($user) < $amount) {
            throw new RuntimeException('Saldo insuficiente.');
        }

        $fromBalance = min((float) $user->balance, $amount);
        $fromBonus = round($amount - $fromBalance, 2);

        $user->balance = round((float) $user->balance - $fromBalance, 2);
        $user->bonus_balance = round((float) $user->bonus_balance - $fromBonus, 2);
        $user->save();

        return [
            'from_balance' => round($fromBalance, 2),
            'from_bonus' => round($fromBonus, 2),
        ];
    }

    /**
     * Credit winnings into normal balance (never into bonus).
     */
    public function credit(User $user, float $amount): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return;
        }

        $user->balance = round((float) $user->balance + $amount, 2);
        $user->save();
    }

    /**
     * Settle a cashout: return stake to its source, profit goes to real balance.
     *
     * @param  array{from_balance: float, from_bonus: float}  $stakeSplit
     */
    public function creditCashout(User $user, float $stake, float $payout, array $stakeSplit): void
    {
        $stake = round($stake, 2);
        $payout = round($payout, 2);
        $fromBalance = round((float) ($stakeSplit['from_balance'] ?? 0), 2);
        $fromBonus = round((float) ($stakeSplit['from_bonus'] ?? 0), 2);

        if ($fromBalance + $fromBonus <= 0) {
            $this->credit($user, $payout);

            return;
        }

        $profit = max(0, round($payout - $stake, 2));

        $this->credit($user, round($fromBalance + $profit, 2));
        $this->creditBonus($user, $fromBonus, applyWagering: false);
    }

    public function creditBonus(User $user, float $amount, bool $applyWagering = true): void
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            return;
        }

        $user->bonus_balance = round((float) $user->bonus_balance + $amount, 2);
        $user->save();

        if ($applyWagering) {
            $this->wagering->addRequirement($user, $amount);
        }
    }

    public function setBalances(User $user, float $balance, float $bonusBalance): void
    {
        $user->balance = round($balance, 2);
        $user->bonus_balance = round($bonusBalance, 2);
        $user->save();
    }
}
