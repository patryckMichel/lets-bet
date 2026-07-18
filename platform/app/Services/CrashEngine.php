<?php

namespace App\Services;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CrashEngine
{
    public const HOUSE_EDGE = 0.08;

    public const MAX_MULTIPLIER = 10.0;

    public const MIN_BET = 1.0;

    public const MAX_BET = 500.0;

    public const BETTING_SECONDS = 8;

    public const GROWTH_RATE = 0.00006;

    public function __construct(
        private GhostTrafficService $ghosts,
        private WalletService $wallet,
        private OpsMetricsService $ops,
        private WageringService $wagering,
    ) {}

    public function houseEdge(): float
    {
        return (float) Setting::getValue('house_edge', self::HOUSE_EDGE);
    }

    public function maxMultiplier(): float
    {
        return (float) Setting::getValue('max_multiplier', self::MAX_MULTIPLIER);
    }

    public function minBet(): float
    {
        return (float) Setting::getValue('min_bet', self::MIN_BET);
    }

    public function maxBet(): float
    {
        return (float) Setting::getValue('max_bet', self::MAX_BET);
    }

    public function getState(?User $user = null): array
    {
        $round = $this->advance(light: true);
        $multiplier = $this->currentMultiplier($round);

        try {
            $history = Cache::remember('crash:history', 3, function () {
                return CrashRound::query()
                    ->where('status', CrashRound::STATUS_CRASHED)
                    ->orderByDesc('round_number')
                    ->limit(20)
                    ->get(['round_number', 'crash_point'])
                    ->map(fn (CrashRound $r) => [
                        'number' => $r->round_number,
                        'crash_point' => (float) $r->crash_point,
                    ])
                    ->values()
                    ->all();
            });
        } catch (\Throwable) {
            $history = CrashRound::query()
                ->where('status', CrashRound::STATUS_CRASHED)
                ->orderByDesc('round_number')
                ->limit(20)
                ->get(['round_number', 'crash_point'])
                ->map(fn (CrashRound $r) => [
                    'number' => $r->round_number,
                    'crash_point' => (float) $r->crash_point,
                ])
                ->values()
                ->all();
        }

        $realBets = $round->relationLoaded('bets')
            ? $round->bets
            : $round->bets()->with('user:id,name')->orderByDesc('id')->limit(20)->get();

        $realMapped = $realBets->map(fn (CrashBet $bet) => [
            'player' => $this->maskName($bet->user?->name ?? 'Jogador'),
            'amount' => (float) $bet->amount,
            'multiplier' => $bet->cashout_multiplier !== null ? (float) $bet->cashout_multiplier : null,
            'payout' => $bet->payout !== null ? (float) $bet->payout : null,
            'status' => $bet->status,
            'ghost' => false,
        ]);

        $myBets = [];
        if ($user) {
            $myBets = $realBets
                ->where('user_id', $user->id)
                ->keyBy('slot')
                ->map(fn (CrashBet $bet) => [
                    'id' => $bet->id,
                    'slot' => $bet->slot,
                    'amount' => (float) $bet->amount,
                    'status' => $bet->status,
                    'auto_cashout_at' => $bet->auto_cashout_at !== null ? (float) $bet->auto_cashout_at : null,
                    'cashout_multiplier' => $bet->cashout_multiplier !== null ? (float) $bet->cashout_multiplier : null,
                    'payout' => $bet->payout !== null ? (float) $bet->payout : null,
                ])
                ->all();

            // Ensure we still see own bets even if not in limited feed query
            if ($myBets === []) {
                $myBets = CrashBet::query()
                    ->where('crash_round_id', $round->id)
                    ->where('user_id', $user->id)
                    ->get()
                    ->keyBy('slot')
                    ->map(fn (CrashBet $bet) => [
                        'id' => $bet->id,
                        'slot' => $bet->slot,
                        'amount' => (float) $bet->amount,
                        'status' => $bet->status,
                        'auto_cashout_at' => $bet->auto_cashout_at !== null ? (float) $bet->auto_cashout_at : null,
                        'cashout_multiplier' => $bet->cashout_multiplier !== null ? (float) $bet->cashout_multiplier : null,
                        'payout' => $bet->payout !== null ? (float) $bet->payout : null,
                    ])
                    ->all();
            }
        }

        $realCount = $realBets->count();
        $feedMeta = $this->ghosts->feedMeta($round, $multiplier, $realCount);
        $liveBets = $this->ghosts->mergeFeed($round, $multiplier, $realMapped);
        $fresh = $user?->fresh();

        return [
            'round' => [
                'id' => $round->id,
                'number' => $round->round_number,
                'status' => $round->status,
                'multiplier' => $multiplier,
                'crash_point' => $round->status === CrashRound::STATUS_CRASHED ? $this->effectiveCrashPoint($round) : null,
                'betting_ends_at' => optional($round->betting_ends_at)?->toIso8601String(),
                'started_at' => optional($round->started_at)?->toIso8601String(),
                'crashed_at' => optional($round->crashed_at)?->toIso8601String(),
                'server_seed_hash' => $round->server_seed_hash,
                'server_seed' => $round->status === CrashRound::STATUS_CRASHED ? $round->server_seed : null,
                'betting_ms_left' => $round->status === CrashRound::STATUS_WAITING && $round->betting_ends_at
                    ? max(0, (int) ($round->betting_ends_at->getTimestampMs() - now()->getTimestampMs()))
                    : 0,
                'players' => $feedMeta['players'],
                'real_players' => $realCount,
                'growth_rate' => self::GROWTH_RATE,
                'max_multiplier' => $this->maxMultiplier(),
            ],
            'history' => $history,
            'bets' => $liveBets,
            'feed' => [
                'label' => $feedMeta['bets_label'],
                'total_won' => $feedMeta['total_won'],
                'ghost_enabled' => $this->ghosts->enabled(),
            ],
            'my_bets' => $myBets,
            'balance' => $fresh ? $this->wallet->available($fresh) : 0,
            'wallet' => $fresh ? [
                'balance' => (float) $fresh->balance,
                'bonus_balance' => (float) $fresh->bonus_balance,
                'total' => $this->wallet->available($fresh),
            ] : null,
            'limits' => [
                'min_bet' => $this->minBet(),
                'max_bet' => $this->maxBet(),
                'max_multiplier' => $this->maxMultiplier(),
                'house_edge' => $this->houseEdge(),
            ],
            'server_now' => now()->toIso8601String(),
        ];
    }

    public function placeBet(User $user, float $amount, int $slot = 1, ?float $autoCashoutAt = null): CrashBet
    {
        if ($slot < 1 || $slot > 2) {
            throw new RuntimeException('Slot inválido.');
        }

        if ($amount < $this->minBet() || $amount > $this->maxBet()) {
            throw new RuntimeException('Aposta fora dos limites ('.$this->minBet().' – '.$this->maxBet().').');
        }

        return DB::transaction(function () use ($user, $amount, $slot, $autoCashoutAt) {
            $round = $this->advance(light: false);

            if ($round->status !== CrashRound::STATUS_WAITING) {
                throw new RuntimeException('Apostas fechadas. Aguarde a próxima rodada.');
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if ($this->wallet->available($lockedUser) < $amount) {
                throw new RuntimeException('Saldo insuficiente.');
            }

            $exists = CrashBet::query()
                ->where('crash_round_id', $round->id)
                ->where('user_id', $lockedUser->id)
                ->where('slot', $slot)
                ->exists();

            if ($exists) {
                throw new RuntimeException('Você já apostou neste painel nesta rodada.');
            }

            if ($autoCashoutAt !== null && ($autoCashoutAt < 1.01 || $autoCashoutAt > $this->maxMultiplier())) {
                throw new RuntimeException('Auto cash-out inválido.');
            }

            $split = $this->wallet->debit($lockedUser, $amount);
            $this->wagering->addProgress($lockedUser, $amount);

            Cache::forget('crash:history');

            $bet = CrashBet::query()->create([
                'crash_round_id' => $round->id,
                'user_id' => $lockedUser->id,
                'slot' => $slot,
                'amount' => $amount,
                'from_balance' => $split['from_balance'],
                'from_bonus' => $split['from_bonus'],
                'auto_cashout_at' => $autoCashoutAt,
                'status' => CrashBet::STATUS_ACTIVE,
            ]);

            $this->ops->recordBet($bet);

            return $bet;
        });
    }

    public function cashout(User $user, int $slot = 1): CrashBet
    {
        return DB::transaction(function () use ($user, $slot) {
            // Locate active bet without locking first
            $betId = CrashBet::query()
                ->where('user_id', $user->id)
                ->where('slot', $slot)
                ->where('status', CrashBet::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->value('id');

            if (! $betId) {
                throw new RuntimeException('Nenhuma aposta ativa neste painel.');
            }

            $bet = CrashBet::query()->whereKey($betId)->firstOrFail();

            // Always lock round BEFORE bet to avoid deadlocks with advanceWrite
            $round = CrashRound::query()
                ->whereKey($bet->crash_round_id)
                ->lockForUpdate()
                ->firstOrFail();

            $bet = CrashBet::query()
                ->whereKey($bet->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($bet->status !== CrashBet::STATUS_ACTIVE) {
                throw new RuntimeException('Esta aposta já foi finalizada.');
            }

            if ($round->status === CrashRound::STATUS_WAITING
                && $round->betting_ends_at
                && now()->greaterThanOrEqualTo($round->betting_ends_at)) {
                $round->status = CrashRound::STATUS_RUNNING;
                $round->started_at = now();
                $round->save();
            }

            if ($round->status === CrashRound::STATUS_WAITING) {
                throw new RuntimeException('Aguarde a decolagem para sacar.');
            }

            if ($round->status === CrashRound::STATUS_CRASHED) {
                $bet->status = CrashBet::STATUS_LOST;
                $bet->save();
                throw new RuntimeException('A rodada já crashou.');
            }

            $multiplier = $this->currentMultiplier($round);

            if ($multiplier >= $this->effectiveCrashPoint($round)) {
                $this->crashRound($round);
                throw new RuntimeException('A rodada já crashou.');
            }

            Cache::forget('crash:history');

            return $this->settleCashout($bet, $multiplier);
        }, 3);
    }

    public function advance(bool $light = false): CrashRound
    {
        if ($light) {
            return $this->advanceLight();
        }

        return $this->advanceWrite();
    }

    protected function advanceLight(): CrashRound
    {
        $round = CrashRound::query()
            ->whereIn('status', [CrashRound::STATUS_WAITING, CrashRound::STATUS_RUNNING])
            ->orderByDesc('id')
            ->first();

        if (! $round) {
            $lastCrashed = CrashRound::query()
                ->where('status', CrashRound::STATUS_CRASHED)
                ->orderByDesc('id')
                ->first();

            if ($lastCrashed && $lastCrashed->crashed_at && $lastCrashed->crashed_at->gt(now()->subSeconds(2))) {
                return $lastCrashed;
            }

            // Create next round without competing long locks when possible
            return $this->advanceWrite();
        }

        // Apply transitions with short conditional updates (no long lock chains)
        if ($round->status === CrashRound::STATUS_WAITING
            && $round->betting_ends_at
            && now()->greaterThanOrEqualTo($round->betting_ends_at)) {
            CrashRound::query()
                ->whereKey($round->id)
                ->where('status', CrashRound::STATUS_WAITING)
                ->update([
                    'status' => CrashRound::STATUS_RUNNING,
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);
            $round->refresh();
        }

        if ($round->status === CrashRound::STATUS_RUNNING) {
            $multiplier = $this->currentMultiplier($round);
            if ($multiplier >= $this->effectiveCrashPoint($round)) {
                $updated = CrashRound::query()
                    ->whereKey($round->id)
                    ->where('status', CrashRound::STATUS_RUNNING)
                    ->update([
                        'status' => CrashRound::STATUS_CRASHED,
                        'crashed_at' => now(),
                        'crash_point' => $this->effectiveCrashPoint($round),
                        'updated_at' => now(),
                    ]);

                if ($updated) {
                    CrashBet::query()
                        ->where('crash_round_id', $round->id)
                        ->where('status', CrashBet::STATUS_ACTIVE)
                        ->update(['status' => CrashBet::STATUS_LOST, 'updated_at' => now()]);
                    Cache::forget('crash:history');
                    $round->refresh();
                    $this->ops->recordRoundCrash($round);
                } else {
                    $round->refresh();
                }
            }
        }

        $round->load(['bets' => fn ($q) => $q->with('user:id,name')->orderByDesc('id')->limit(20)]);

        return $round;
    }

    protected function advanceWrite(): CrashRound
    {
        return DB::transaction(function () {
            $round = CrashRound::query()
                ->whereIn('status', [CrashRound::STATUS_WAITING, CrashRound::STATUS_RUNNING])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $round) {
                $lastCrashed = CrashRound::query()
                    ->where('status', CrashRound::STATUS_CRASHED)
                    ->orderByDesc('id')
                    ->first();

                if ($lastCrashed && $lastCrashed->crashed_at && $lastCrashed->crashed_at->gt(now()->subSeconds(2))) {
                    return $lastCrashed->load(['bets.user']);
                }

                return $this->createWaitingRound();
            }

            if ($round->status === CrashRound::STATUS_WAITING
                && $round->betting_ends_at
                && now()->greaterThanOrEqualTo($round->betting_ends_at)) {
                $round->status = CrashRound::STATUS_RUNNING;
                $round->started_at = now();
                $round->save();
            }

            if ($round->status === CrashRound::STATUS_RUNNING) {
                // Auto-cashouts only on write path (bet/cashout/transition), not every poll
                if ($round->bets()->where('status', CrashBet::STATUS_ACTIVE)->whereNotNull('auto_cashout_at')->exists()) {
                    $this->processAutoCashouts($round);
                }

                $multiplier = $this->currentMultiplier($round);

                if ($multiplier >= $this->effectiveCrashPoint($round)) {
                    $this->crashRound($round);
                    Cache::forget('crash:history');

                    return $round->fresh(['bets.user']);
                }
            }

            return $round->load(['bets' => fn ($q) => $q->with('user:id,name')->orderByDesc('id')->limit(20)]);
        }, 3);
    }

    public function currentMultiplier(CrashRound $round): float
    {
        if ($round->status === CrashRound::STATUS_WAITING || ! $round->started_at) {
            return 1.0;
        }

        if ($round->status === CrashRound::STATUS_CRASHED) {
            return $this->effectiveCrashPoint($round);
        }

        $elapsedMs = max(0, (int) (now()->getTimestampMs() - $round->started_at->getTimestampMs()));
        $mult = exp(self::GROWTH_RATE * $elapsedMs);
        $mult = min($this->maxMultiplier(), $mult);

        return floor($mult * 100) / 100;
    }

    /**
     * Crash target capped by current house max (avoids stuck rounds after lowering max_multiplier).
     */
    public function effectiveCrashPoint(CrashRound $round): float
    {
        return round(min((float) $round->crash_point, $this->maxMultiplier()), 2);
    }

    public function generateCrashPoint(string $serverSeed): float
    {
        $hash = hash('sha256', $serverSeed);
        $roll = hexdec(substr($hash, 0, 8)) / 4294967295;
        $edge = $this->houseEdge();
        $max = $this->maxMultiplier();

        if ($roll < $edge) {
            return 1.00;
        }

        $r = ($roll - $edge) / (1 - $edge);
        $r = min(0.999999, max(0.0, $r));
        $crash = (1 - $edge) / (1 - $r);

        return round(max(1.00, min($max, $crash)), 2);
    }

    protected function createWaitingRound(): CrashRound
    {
        // Serialize concurrent pollers creating the next round (Postgres).
        DB::select('select pg_advisory_xact_lock(?)', [872314001]);

        $existing = CrashRound::query()
            ->whereIn('status', [CrashRound::STATUS_WAITING, CrashRound::STATUS_RUNNING])
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing->load(['bets' => fn ($q) => $q->with('user:id,name')->orderByDesc('id')->limit(20)]);
        }

        $lastNumber = (int) CrashRound::query()->max('round_number');
        $serverSeed = bin2hex(random_bytes(32));
        $crashPoint = $this->generateCrashPoint($serverSeed);

        try {
            return CrashRound::query()->create([
                'round_number' => $lastNumber + 1,
                'status' => CrashRound::STATUS_WAITING,
                'crash_point' => $crashPoint,
                'server_seed' => $serverSeed,
                'server_seed_hash' => hash('sha256', $serverSeed),
                'betting_ends_at' => now()->addSeconds(self::BETTING_SECONDS),
            ]);
        } catch (UniqueConstraintViolationException) {
            $round = CrashRound::query()
                ->whereIn('status', [CrashRound::STATUS_WAITING, CrashRound::STATUS_RUNNING])
                ->orderByDesc('id')
                ->first();

            if ($round) {
                return $round->load(['bets' => fn ($q) => $q->with('user:id,name')->orderByDesc('id')->limit(20)]);
            }

            throw new RuntimeException('Falha ao criar rodada.');
        }
    }

    protected function crashRound(CrashRound $round): void
    {
        $effective = $this->effectiveCrashPoint($round);
        if ((float) $round->crash_point !== $effective) {
            $round->crash_point = $effective;
        }

        $round->status = CrashRound::STATUS_CRASHED;
        $round->crashed_at = now();
        $round->save();

        CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('status', CrashBet::STATUS_ACTIVE)
            ->update(['status' => CrashBet::STATUS_LOST]);

        $this->ops->recordRoundCrash($round);
    }

    protected function processAutoCashouts(CrashRound $round): void
    {
        $multiplier = $this->currentMultiplier($round);

        $bets = CrashBet::query()
            ->where('crash_round_id', $round->id)
            ->where('status', CrashBet::STATUS_ACTIVE)
            ->whereNotNull('auto_cashout_at')
            ->where('auto_cashout_at', '<=', $multiplier)
            ->lockForUpdate()
            ->get();

        foreach ($bets as $bet) {
            $target = min((float) $bet->auto_cashout_at, $this->effectiveCrashPoint($round) - 0.01);
            if ($target < 1.01 || $multiplier >= $this->effectiveCrashPoint($round)) {
                continue;
            }
            $this->settleCashout($bet, (float) $bet->auto_cashout_at);
        }
    }

    protected function settleCashout(CrashBet $bet, float $multiplier): CrashBet
    {
        $multiplier = floor($multiplier * 100) / 100;
        $payout = round((float) $bet->amount * $multiplier, 2);

        $bet->status = CrashBet::STATUS_CASHED_OUT;
        $bet->cashout_multiplier = $multiplier;
        $bet->payout = $payout;
        $bet->cashed_out_at = now();
        $bet->save();

        $user = User::query()->whereKey($bet->user_id)->lockForUpdate()->firstOrFail();
        $this->wallet->creditCashout($user, (float) $bet->amount, $payout, [
            'from_balance' => (float) ($bet->from_balance ?? 0),
            'from_bonus' => (float) ($bet->from_bonus ?? 0),
        ]);

        $this->ops->recordCashout($bet);

        return $bet;
    }

    protected function maskName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'j****';
        }

        return Str::lower(Str::substr($name, 0, 1)).'****';
    }
}
