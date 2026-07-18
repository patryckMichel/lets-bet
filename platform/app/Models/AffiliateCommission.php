<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    /** @deprecated use STATUS_OPEN */
    public const STATUS_PENDING = self::STATUS_OPEN;

    /** @deprecated use STATUS_PAID */
    public const STATUS_CREDITED = self::STATUS_PAID;

    protected $fillable = [
        'affiliate_id',
        'deposit_id',
        'withdrawal_id',
        'base_amount',
        'commission_amount',
        'status',
        'credit_at',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'credit_at' => 'datetime',
            'credited_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
