<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpsEvent extends Model
{
    protected $fillable = [
        'event',
        'user_id',
        'player_session_id',
        'round_id',
        'bet_id',
        'amount',
        'multiplier',
        'meta',
        'ip',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'multiplier' => 'decimal:2',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playerSession(): BelongsTo
    {
        return $this->belongsTo(PlayerSession::class);
    }
}
