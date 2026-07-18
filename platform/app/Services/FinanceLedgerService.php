<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class FinanceLedgerService
{
    public function houseBalance(): float
    {
        $in = (float) FinanceEntry::query()->where('direction', FinanceEntry::DIR_IN)->sum('amount');
        $out = (float) FinanceEntry::query()->where('direction', FinanceEntry::DIR_OUT)->sum('amount');

        return round($in - $out, 2);
    }

    public function record(
        string $type,
        string $direction,
        float $amount,
        ?Model $reference = null,
        ?User $admin = null,
        ?string $note = null,
    ): FinanceEntry {
        $amount = round(abs($amount), 2);

        return FinanceEntry::query()->create([
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'admin_id' => $admin?->id,
            'note' => $note,
        ]);
    }
}
