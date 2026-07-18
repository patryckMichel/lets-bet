<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrashBet extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CASHED_OUT = 'cashed_out';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'crash_round_id',
        'user_id',
        'slot',
        'amount',
        'from_balance',
        'from_bonus',
        'auto_cashout_at',
        'cashout_multiplier',
        'payout',
        'status',
        'cashed_out_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'from_balance' => 'decimal:2',
            'from_bonus' => 'decimal:2',
            'auto_cashout_at' => 'decimal:2',
            'cashout_multiplier' => 'decimal:2',
            'payout' => 'decimal:2',
            'cashed_out_at' => 'datetime',
            'slot' => 'integer',
        ];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(CrashRound::class, 'crash_round_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
