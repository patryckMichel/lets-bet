<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrucoSeat extends Model
{
    protected $fillable = [
        'truco_match_id',
        'user_id',
        'is_ghost',
        'display_name',
        'team',
        'seat_index',
        'from_balance',
        'from_bonus',
    ];

    protected function casts(): array
    {
        return [
            'is_ghost' => 'boolean',
            'from_balance' => 'decimal:2',
            'from_bonus' => 'decimal:2',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TrucoMatch::class, 'truco_match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
