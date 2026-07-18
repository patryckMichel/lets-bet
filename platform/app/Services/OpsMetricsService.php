<?php

namespace App\Services;

use App\Models\CrashBet;
use App\Models\CrashRound;
use App\Models\OpsEvent;
use App\Models\OpsHourlyStat;
use App\Models\PlayerSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Session;
use Throwable;

class OpsMetricsService
{
    public const EVENT_LOGIN = 'auth.login';

    public const EVENT_LOGOUT = 'auth.logout';

    public const EVENT_HEARTBEAT = 'presence.heartbeat';

    public const EVENT_BET = 'crash.bet';

    public const EVENT_CASHOUT = 'crash.cashout';

    public const EVENT_ROUND_CRASH = 'crash.round_crash';

    public const EVENT_DEPOSIT = 'wallet.deposit_paid';

    public const EVENT_WITHDRAWAL = 'wallet.withdrawal_requested';

    private const HEARTBEAT_SECONDS = 60;

    private const ONLINE_WINDOW_SECONDS = 180;

    public function startSession(User $user): ?PlayerSession
    {
        return $this->safe(function () use ($user) {
            $now = now();
            $sessionKey = Session::getId() ?: null;

            PlayerSession::query()
                ->where('user_id', $user->id)
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->get()
                ->each(function (PlayerSession $old) use ($now) {
                    $old->ended_at = $now;
                    $old->duration_seconds = max(0, (int) $old->started_at->diffInSeconds($now));
                    $old->end_reason = 'replaced';
                    $old->save();
                });

            $session = PlayerSession::query()->create([
                'user_id' => $user->id,
                'session_key' => $sessionKey,
                'ip' => Request::ip(),
                'user_agent' => substr((string) Request::userAgent(), 0, 255),
                'started_at' => $now,
                'last_seen_at' => $now,
            ]);

            if ($sessionKey) {
                Session::put('ops_player_session_id', $session->id);
            }

            $user->forceFill([
                'last_login_at' => $now,
                'last_seen_at' => $now,
            ])->save();

            $this->recordEvent(self::EVENT_LOGIN, $user, [
                'player_session_id' => $session->id,
            ]);

            $this->bumpHourly([
                'logins' => 1,
            ], uniquePlayerId: $user->id);

            $this->refreshOnlinePeak();

            return $session;
        });
    }

    public function endSession(User $user, string $reason = 'logout'): void
    {
        $this->safe(function () use ($user, $reason) {
            $now = now();
            $sessionId = Session::pull('ops_player_session_id');

            $session = null;
            if ($sessionId) {
                $session = PlayerSession::query()
                    ->whereKey($sessionId)
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->first();
            }

            if (! $session) {
                $session = PlayerSession::query()
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->orderByDesc('id')
                    ->first();
            }

            if ($session) {
                $session->ended_at = $now;
                $session->last_seen_at = $now;
                $session->duration_seconds = max(0, (int) $session->started_at->diffInSeconds($now));
                $session->end_reason = $reason;
                $session->save();
            }

            $this->recordEvent(self::EVENT_LOGOUT, $user, [
                'player_session_id' => $session?->id,
                'meta' => ['reason' => $reason],
            ]);

            $this->bumpHourly(['logouts' => 1]);
        });
    }

    public function heartbeat(User $user): void
    {
        $this->safe(function () use ($user) {
            $cacheKey = 'ops:hb:'.$user->id;
            if (! Cache::add($cacheKey, 1, self::HEARTBEAT_SECONDS)) {
                return;
            }

            $now = now();
            $user->forceFill(['last_seen_at' => $now])->save();

            $sessionId = Session::get('ops_player_session_id');
            $session = null;

            if ($sessionId) {
                $session = PlayerSession::query()
                    ->whereKey($sessionId)
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->first();
            }

            if (! $session) {
                $session = PlayerSession::query()
                    ->where('user_id', $user->id)
                    ->whereNull('ended_at')
                    ->orderByDesc('id')
                    ->first();

                if ($session) {
                    Session::put('ops_player_session_id', $session->id);
                }
            }

            if ($session) {
                $session->forceFill(['last_seen_at' => $now])->save();
            }

            // Presence only — avoid flooding ops_events every minute.
            $this->bumpHourly(['heartbeats' => 1], uniquePlayerId: $user->id);
            $this->refreshOnlinePeak();
        });
    }

    public function recordBet(CrashBet $bet): void
    {
        $this->safe(function () use ($bet) {
            $user = User::query()->find($bet->user_id);
            $amount = round((float) $bet->amount, 2);

            $this->recordEvent(self::EVENT_BET, $user, [
                'round_id' => $bet->crash_round_id,
                'bet_id' => $bet->id,
                'amount' => $amount,
                'meta' => [
                    'slot' => $bet->slot,
                    'auto_cashout_at' => $bet->auto_cashout_at,
                ],
            ]);

            $this->bumpHourly([
                'bets_count' => 1,
                'bets_amount' => $amount,
            ], uniquePlayerId: $bet->user_id);
        });
    }

    public function recordCashout(CrashBet $bet): void
    {
        $this->safe(function () use ($bet) {
            $user = User::query()->find($bet->user_id);
            $payout = round((float) $bet->payout, 2);
            $mult = round((float) $bet->cashout_multiplier, 2);

            $this->recordEvent(self::EVENT_CASHOUT, $user, [
                'round_id' => $bet->crash_round_id,
                'bet_id' => $bet->id,
                'amount' => $payout,
                'multiplier' => $mult,
                'meta' => [
                    'stake' => (float) $bet->amount,
                    'slot' => $bet->slot,
                ],
            ]);

            $this->bumpHourly([
                'cashouts_count' => 1,
                'cashouts_amount' => $payout,
            ], uniquePlayerId: $bet->user_id);
        });
    }

    public function recordRoundCrash(CrashRound $round): void
    {
        $this->safe(function () use ($round) {
            $bets = CrashBet::query()
                ->where('crash_round_id', $round->id)
                ->get(['id', 'user_id', 'amount', 'status', 'payout']);

            $wagered = round((float) $bets->sum('amount'), 2);
            $paid = round((float) $bets->where('status', CrashBet::STATUS_CASHED_OUT)->sum('payout'), 2);
            $lostAmount = round((float) $bets->where('status', CrashBet::STATUS_LOST)->sum('amount'), 2);
            $lostCount = $bets->where('status', CrashBet::STATUS_LOST)->count();
            $crashPoint = round((float) $round->crash_point, 2);
            $ggr = round($wagered - $paid, 2);

            $this->recordEvent(self::EVENT_ROUND_CRASH, null, [
                'round_id' => $round->id,
                'amount' => $ggr,
                'multiplier' => $crashPoint,
                'meta' => [
                    'round_number' => $round->round_number,
                    'wagered' => $wagered,
                    'paid' => $paid,
                    'lost_amount' => $lostAmount,
                    'bets' => $bets->count(),
                ],
            ]);

            $this->bumpHourly([
                'rounds_count' => 1,
                'crash_point_sum' => $crashPoint,
                'round_wagered' => $wagered,
                'round_paid' => $paid,
                'ggr' => $ggr,
                'losses_count' => $lostCount,
                'losses_amount' => $lostAmount,
            ], crashPointMax: $crashPoint);
        });
    }

    public function recordDepositPaid(int $userId, float $amount, ?int $depositId = null): void
    {
        $this->safe(function () use ($userId, $amount, $depositId) {
            $amount = round($amount, 2);
            $user = User::query()->find($userId);

            $this->recordEvent(self::EVENT_DEPOSIT, $user, [
                'amount' => $amount,
                'meta' => ['deposit_id' => $depositId],
            ]);

            $this->bumpHourly([
                'deposits_count' => 1,
                'deposits_amount' => $amount,
            ], uniquePlayerId: $userId);
        });
    }

    public function recordWithdrawalRequested(int $userId, float $amount, ?int $withdrawalId = null): void
    {
        $this->safe(function () use ($userId, $amount, $withdrawalId) {
            $amount = round($amount, 2);
            $user = User::query()->find($userId);

            $this->recordEvent(self::EVENT_WITHDRAWAL, $user, [
                'amount' => $amount,
                'meta' => ['withdrawal_id' => $withdrawalId],
            ]);

            $this->bumpHourly([
                'withdrawals_count' => 1,
                'withdrawals_amount' => $amount,
            ], uniquePlayerId: $userId);
        });
    }

    public function onlineCount(): int
    {
        return User::query()
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW_SECONDS))
            ->count();
    }

    /**
     * @param  array<string, int|float>  $increments
     */
    protected function bumpHourly(
        array $increments,
        ?int $uniquePlayerId = null,
        ?float $crashPointMax = null,
    ): void {
        $hourStart = now()->startOfHour();

        $stat = OpsHourlyStat::query()->firstOrCreate(
            ['hour_start' => $hourStart],
            []
        );

        foreach ($increments as $column => $value) {
            if (! in_array($column, $stat->getFillable(), true)) {
                continue;
            }

            if (is_float($value) || str_contains($column, 'amount') || in_array($column, ['ggr', 'crash_point_sum', 'round_wagered', 'round_paid'], true)) {
                $stat->{$column} = round((float) $stat->{$column} + (float) $value, 2);
            } else {
                $stat->{$column} = (int) $stat->{$column} + (int) $value;
            }
        }

        if ($crashPointMax !== null) {
            $stat->crash_point_max = max((float) $stat->crash_point_max, $crashPointMax);
        }

        if ($uniquePlayerId) {
            $setKey = 'ops:unique:'.$hourStart->format('YmdH');
            $added = Cache::get($setKey, []);
            if (! in_array($uniquePlayerId, $added, true)) {
                $added[] = $uniquePlayerId;
                Cache::put($setKey, $added, now()->addHours(2));
                $stat->unique_players = count($added);
            }
        }

        $stat->save();
    }

    protected function refreshOnlinePeak(): void
    {
        $online = $this->onlineCount();
        $hourStart = now()->startOfHour();
        $stat = OpsHourlyStat::query()->firstOrCreate(['hour_start' => $hourStart], []);
        if ($online > (int) $stat->online_peak) {
            $stat->online_peak = $online;
            $stat->save();
        }
    }

    /**
     * @param  array{
     *   player_session_id?: int|null,
     *   round_id?: int|null,
     *   bet_id?: int|null,
     *   amount?: float|null,
     *   multiplier?: float|null,
     *   meta?: array<string, mixed>|null
     * }  $data
     */
    protected function recordEvent(string $event, ?User $user, array $data = []): void
    {
        $sessionId = $data['player_session_id'] ?? Session::get('ops_player_session_id');

        OpsEvent::query()->create([
            'event' => $event,
            'user_id' => $user?->id,
            'player_session_id' => $sessionId,
            'round_id' => $data['round_id'] ?? null,
            'bet_id' => $data['bet_id'] ?? null,
            'amount' => $data['amount'] ?? null,
            'multiplier' => $data['multiplier'] ?? null,
            'meta' => $data['meta'] ?? null,
            'ip' => Request::ip(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T|null
     */
    protected function safe(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            Log::warning('ops_metrics_failed', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
