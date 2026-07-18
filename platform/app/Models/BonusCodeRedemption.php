<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusCodeRedemption extends Model
{
    protected $fillable = [
        'user_id',
        'bonus_code_id',
        'deposit_id',
        'period_key',
        'bonus_credited',
    ];

    protected function casts(): array
    {
        return [
            'bonus_credited' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bonusCode(): BelongsTo
    {
        return $this->belongsTo(BonusCode::class);
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }
}
