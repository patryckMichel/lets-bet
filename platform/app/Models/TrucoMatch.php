<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrucoMatch extends Model
{
    public const MODE_1V1 = '1v1';

    public const MODE_2V2 = '2v2';

    public const STATUS_WAITING = 'waiting';

    public const STATUS_PLAYING = 'playing';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    public const WINNER_US = 'us';

    public const WINNER_THEM = 'them';

    protected $fillable = [
        'mode',
        'stake',
        'status',
        'code',
        'host_user_id',
        'score_us',
        'score_them',
        'hand_value',
        'target_winner',
        'edge_roll',
        'house_edge',
        'state',
        'from_balance',
        'from_bonus',
        'stake_splits',
        'settled_at',
        'turn_deadline',
    ];

    protected function casts(): array
    {
        return [
            'stake' => 'decimal:2',
            'edge_roll' => 'decimal:6',
            'house_edge' => 'decimal:4',
            'state' => 'array',
            'from_balance' => 'decimal:2',
            'from_bonus' => 'decimal:2',
            'stake_splits' => 'array',
            'settled_at' => 'datetime',
            'turn_deadline' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }

    public function seats(): HasMany
    {
        return $this->hasMany(TrucoSeat::class);
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }
}
