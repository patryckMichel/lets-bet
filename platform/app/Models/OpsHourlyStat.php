<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OpsHourlyStat extends Model
{
    protected $fillable = [
        'hour_start',
        'logins',
        'logouts',
        'unique_players',
        'online_peak',
        'heartbeats',
        'bets_count',
        'bets_amount',
        'cashouts_count',
        'cashouts_amount',
        'losses_count',
        'losses_amount',
        'rounds_count',
        'crash_point_sum',
        'crash_point_max',
        'round_wagered',
        'round_paid',
        'ggr',
        'deposits_count',
        'deposits_amount',
        'withdrawals_count',
        'withdrawals_amount',
    ];

    protected function casts(): array
    {
        return [
            'hour_start' => 'datetime',
            'bets_amount' => 'decimal:2',
            'cashouts_amount' => 'decimal:2',
            'losses_amount' => 'decimal:2',
            'crash_point_sum' => 'decimal:2',
            'crash_point_max' => 'decimal:2',
            'round_wagered' => 'decimal:2',
            'round_paid' => 'decimal:2',
            'ggr' => 'decimal:2',
            'deposits_amount' => 'decimal:2',
            'withdrawals_amount' => 'decimal:2',
        ];
    }

    public function avgCrashPoint(): float
    {
        if ((int) $this->rounds_count <= 0) {
            return 0.0;
        }

        return round((float) $this->crash_point_sum / (int) $this->rounds_count, 2);
    }
}
