<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    protected $fillable = [
        'user_id',
        'referral_code',
        'commission_percent',
        'pix_key',
        'credit_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'commission_percent' => 'decimal:2',
            'credit_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(User::class, 'affiliate_id');
    }

    public function bonusCodes(): HasMany
    {
        return $this->hasMany(BonusCode::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = 'AFF'.strtoupper(Str::random(8));
        } while (self::query()->where('referral_code', $code)->exists());

        return $code;
    }

    public function hasPixKey(): bool
    {
        return filled(trim((string) $this->pix_key));
    }
}
