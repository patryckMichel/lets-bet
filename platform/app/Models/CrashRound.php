<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrashRound extends Model
{
    public const STATUS_WAITING = 'waiting';

    public const STATUS_RUNNING = 'running';

    public const STATUS_CRASHED = 'crashed';

    protected $fillable = [
        'round_number',
        'status',
        'crash_point',
        'server_seed',
        'server_seed_hash',
        'betting_ends_at',
        'started_at',
        'crashed_at',
    ];

    protected function casts(): array
    {
        return [
            'crash_point' => 'decimal:2',
            'betting_ends_at' => 'datetime',
            'started_at' => 'datetime',
            'crashed_at' => 'datetime',
        ];
    }

    public function bets(): HasMany
    {
        return $this->hasMany(CrashBet::class);
    }
}
