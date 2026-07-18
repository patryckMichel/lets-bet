<?php

namespace App\Services;

use App\Models\CrashRound;
use App\Models\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class GhostTrafficService
{
    /** @var list<string> */
    private array $names = [
        'lucas', 'ana', 'bruno', 'carla', 'diego', 'elena', 'felipe', 'gabi',
        'hugo', 'iris', 'joao', 'karen', 'leo', 'marina', 'nico', 'olivia',
        'pedro', 'quinn', 'rafa', 'sofia', 'tiago', 'uma', 'vitor', 'wendy',
        'yago', 'zara', 'bia', 'caio', 'duda', 'enzo', 'fernanda', 'gustavo',
        'helena', 'igor', 'julia', 'kaio', 'lara', 'mateus', 'nina', 'otavio',
    ];

    public function enabled(): bool
    {
        return Cache::remember('setting:ghost_bets_enabled:bool', 60, function () {
            return Setting::bool('ghost_bets_enabled', true);
        });
    }

    public function playerCount(CrashRound $round, float $multiplier): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        $min = max(0, Setting::int('ghost_players_min', 1200));
        $max = max($min, Setting::int('ghost_players_max', 2500));

        // Stable base per round inside the configured range
        $span = max(1, $max - $min);
        $base = $min + ($this->seedInt('players-'.$round->id) % ($span + 1));

        // Slight live variation while flying (keeps feel of activity without wild jumps)
        if ($round->status === CrashRound::STATUS_RUNNING) {
            $jitter = $this->seedInt('jitter-'.$round->id.'-'.(int) floor($multiplier * 10)) % 37;
            $base = min($max, $base + $jitter);
        }

        if ($round->status === CrashRound::STATUS_WAITING) {
            $progress = 1;
            if ($round->betting_ends_at) {
                $total = max(1, 8000);
                $left = max(0, $round->betting_ends_at->getTimestampMs() - now()->getTimestampMs());
                $progress = 1 - min(1, $left / $total);
            }
            $base = (int) round($min + ($base - $min) * (0.55 + 0.45 * $progress));
        }

        return $base;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $realBets
     * @return list<array<string, mixed>>
     */
    public function mergeFeed(CrashRound $round, float $multiplier, Collection $realBets): array
    {
        if (! $this->enabled()) {
            return $realBets->values()->all();
        }

        $ghosts = Cache::remember(
            'ghost:feed:'.$round->id.':'.$round->status.':'.((int) floor($multiplier * 2)),
            2,
            fn () => $this->buildGhostBets($round, $multiplier)
        );
        $merged = $realBets->values()->all();

        foreach ($ghosts as $ghost) {
            $merged[] = $ghost;
        }

        // Real bets first, then ghosts sorted by newest-looking activity
        usort($merged, function (array $a, array $b) {
            $aReal = ($a['ghost'] ?? false) ? 0 : 1;
            $bReal = ($b['ghost'] ?? false) ? 0 : 1;
            if ($aReal !== $bReal) {
                return $bReal <=> $aReal;
            }

            $aScore = ($a['multiplier'] ?? 0) + (($a['status'] ?? '') === 'cashed_out' ? 10 : 0);
            $bScore = ($b['multiplier'] ?? 0) + (($b['status'] ?? '') === 'cashed_out' ? 10 : 0);

            return $bScore <=> $aScore;
        });

        return array_slice($merged, 0, 25);
    }

    public function feedMeta(CrashRound $round, float $multiplier, int $realBetCount): array
    {
        $players = $this->playerCount($round, $multiplier) + $realBetCount;

        if (! $this->enabled()) {
            return [
                'players' => $realBetCount,
                'bets_label' => $realBetCount.' apostas',
                'total_won' => 0.0,
            ];
        }

        $totalSlots = max($players, $realBetCount);
        $activeLike = (int) round($players * (0.55 + ($this->seedInt('active-'.$round->id) % 20) / 100));
        $activeLike = max($realBetCount, min($totalSlots, $activeLike));

        $ghosts = $this->buildGhostBets($round, $multiplier);
        $totalWon = 0.0;
        foreach ($ghosts as $g) {
            if (($g['status'] ?? '') === 'cashed_out' && isset($g['payout'])) {
                $totalWon += (float) $g['payout'];
            }
        }

        return [
            'players' => $players,
            'bets_label' => $activeLike.'/'.$totalSlots.' Apostas',
            'total_won' => round($totalWon, 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildGhostBets(CrashRound $round, float $multiplier): array
    {
        $count = 18 + ($this->seedInt('feed-count-'.$round->id) % 10); // 18-27 rows
        $bets = [];

        for ($i = 0; $i < $count; $i++) {
            $seed = 'bet-'.$round->id.'-'.$i;
            $name = $this->names[$this->seedInt($seed.'-name') % count($this->names)];
            $amount = $this->fakeAmount($seed);

            $status = 'active';
            $cashoutAt = null;
            $payout = null;

            // Predefine a cashout target for some ghosts
            $willCash = ($this->seedInt($seed.'-cash') % 100) < 62;
            $target = 1.1 + (($this->seedInt($seed.'-target') % 800) / 100); // 1.10 .. 9.09

            if ($round->status === CrashRound::STATUS_CRASHED) {
                $crash = (float) $round->crash_point;
                if ($willCash && $target < $crash) {
                    $status = 'cashed_out';
                    $cashoutAt = round($target, 2);
                    $payout = round($amount * $cashoutAt, 2);
                } else {
                    $status = 'lost';
                }
            } elseif ($round->status === CrashRound::STATUS_RUNNING) {
                if ($willCash && $multiplier >= $target) {
                    $status = 'cashed_out';
                    $cashoutAt = round($target, 2);
                    $payout = round($amount * $cashoutAt, 2);
                }
            }

            $bets[] = [
                'player' => $this->mask($name),
                'amount' => $amount,
                'multiplier' => $cashoutAt,
                'payout' => $payout,
                'status' => $status,
                'ghost' => true,
            ];
        }

        return $bets;
    }

    protected function fakeAmount(string $seed): float
    {
        $presets = [1, 2, 5, 10, 20, 50, 100, 150, 200, 300, 500];
        $pick = $presets[$this->seedInt($seed.'-amt') % count($presets)];

        // Occasional decimals
        if ($this->seedInt($seed.'-dec') % 4 === 0) {
            return round($pick + (($this->seedInt($seed.'-cent') % 90) / 100), 2);
        }

        return (float) $pick;
    }

    protected function mask(string $name): string
    {
        $first = mb_substr($name, 0, 1);

        return mb_strtolower($first).'****';
    }

    protected function seedInt(string $key): int
    {
        return abs(crc32($key));
    }
}
