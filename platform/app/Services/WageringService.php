<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class WageringService
{
    public function multiplier(): float
    {
        return max(0, (float) Setting::getValue('wagering_multiplier', 20));
    }

    public function addRequirement(User $user, float $baseAmount): void
    {
        $baseAmount = round($baseAmount, 2);
        if ($baseAmount <= 0) {
            return;
        }

        $add = round($baseAmount * $this->multiplier(), 2);
        if ($add <= 0) {
            return;
        }

        $user->wagering_required = round((float) $user->wagering_required + $add, 2);
        $user->save();
    }

    public function addProgress(User $user, float $stake): void
    {
        $stake = round($stake, 2);
        if ($stake <= 0) {
            return;
        }

        $required = round((float) $user->wagering_required, 2);
        $progress = round((float) $user->wagering_progress, 2);
        $user->wagering_progress = round(min($required, $progress + $stake), 2);
        $user->save();
    }

    public function isMet(User $user): bool
    {
        $required = round((float) $user->wagering_required, 2);
        if ($required <= 0) {
            return true;
        }

        return round((float) $user->wagering_progress, 2) >= $required;
    }

    public function remaining(User $user): float
    {
        return max(0, round((float) $user->wagering_required - (float) $user->wagering_progress, 2));
    }

    /**
     * @return array{required: float, progress: float, remaining: float, met: bool, multiplier: float, percent: float}
     */
    public function status(User $user): array
    {
        $required = round((float) $user->wagering_required, 2);
        $progress = round((float) $user->wagering_progress, 2);
        $remaining = max(0, round($required - $progress, 2));
        $percent = $required > 0 ? min(100, round($progress / $required * 100, 1)) : 100.0;

        return [
            'required' => $required,
            'progress' => $progress,
            'remaining' => $remaining,
            'met' => $remaining <= 0,
            'multiplier' => $this->multiplier(),
            'percent' => $percent,
        ];
    }
}
