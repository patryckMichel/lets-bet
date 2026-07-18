<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'amount',
        'pix_key',
        'pix_key_type',
        'asaas_transfer_id',
        'status',
        'provider_status',
        'admin_note',
        'provider_payload',
        'processed_at',
        'source',
        'affiliate_id',
    ];

    public const SOURCE_PLAYER = 'player';

    public const SOURCE_AFFILIATE = 'affiliate_commission';

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'provider_payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function isAffiliateCommission(): bool
    {
        return $this->source === self::SOURCE_AFFILIATE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function canPay(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPROVED], true)
            && blank($this->asaas_transfer_id);
    }
}
