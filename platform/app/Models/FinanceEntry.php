<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FinanceEntry extends Model
{
    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_AFFILIATE_PAYOUT = 'affiliate_payout';

    public const TYPE_BANK_TRANSFER = 'bank_transfer';

    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    public const TYPE_GATEWAY_FEE = 'gateway_fee';

    public const DIR_IN = 'in';

    public const DIR_OUT = 'out';

    protected $fillable = [
        'type',
        'direction',
        'amount',
        'reference_type',
        'reference_id',
        'admin_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function signedAmount(): float
    {
        $amount = (float) $this->amount;

        return $this->direction === self::DIR_IN ? $amount : -$amount;
    }
}
