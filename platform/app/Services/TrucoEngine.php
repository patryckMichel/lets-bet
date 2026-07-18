<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TrucoMatch;
use App\Models\TrucoSeat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TrucoEngine
{
    public const STAKES = [5, 10, 20, 25, 30, 50, 100, 200, 300, 500];

    /** @var list<string> */
    private const RANKS = ['4', '5', '6', '7', 'Q', 'J', 'K', 'A', '2', '3'];

    /** @var list<string> */
    private const SUITS = ['D', 'S', 'H', 'C'];

    private const GHOST_NAMES = [
        'Rafael', 'Bruno', 'Lucas', 'Diego', 'Felipe', 'André', 'Thiago', 'Gustavo',
        'Camila', 'Juliana', 'Fernanda', 'Patrícia', 'Mariana', 'Aline',
    ];

    public function __construct(
        private WalletService $wallet,
        private WageringService $wagering,
        private FinanceLedgerService $ledger,
    ) {}

    public function houseEdge(): float
    {
        return max(0, min(0.5, (float) Setting::getValue('truco_house_edge', 0.05)));
    }

    public function turnTimeoutSeconds(): int
    {
        return max(5, min(120, (int) Setting::getValue('truco_turn_timeout_seconds', 60)));
    }

    /** @return list<int|float> */
    public function stakes(): array
    {
        return self::STAKES;
    }

    public function start1v1(User $user, float $stake): TrucoMatch
    {
        $stake = $this->assertStake($stake);

        return DB::transaction(function () use ($user, $stake) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $split = $this->debitHuman($locked, $stake);
            [$target, $roll, $edge] = $this->rollTarget();

            $match = TrucoMatch::query()->create([
                'mode' => TrucoMatch::MODE_1V1,
                'stake' => $stake,
                'status' => TrucoMatch::STATUS_PLAYING,
                'host_user_id' => $locked->id,
                'score_us' => 0,
                'score_them' => 0,
                'hand_value' => 1,
                'target_winner' => $target,
                'edge_roll' => $roll,
                'house_edge' => $edge,
                'from_balance' => $split['from_balance'],
                'from_bonus' => $split['from_bonus'],
                'stake_splits' => [$locked->id => $split],
                'state' => [],
            ]);

            $this->recordEntry(
                $match,
                $stake,
                \App\Models\FinanceEntry::TYPE_TRUCO_ENTRY,
                \App\Models\FinanceEntry::DIR_IN,
                'Truco 1v1 entry user #'.$locked->id
            );

            $this->createSeat($match, $locked->id, false, $locked->name, 'us', 0, $split);
            $this->createSeat($match, null, true, $this->ghostName(), 'them', 1);

            $match->state = $this->newHandState($match, true);
            $this->bumpDeadline($match);
            $match->save();

            return $match->fresh(['seats']);
        });
    }

    public function create2v2Room(User $host, float $stake): TrucoMatch
    {
        $stake = $this->assertStake($stake);

        return DB::transaction(function () use ($host, $stake) {
            $code = $this->uniqueCode();
            $match = TrucoMatch::query()->create([
                'mode' => TrucoMatch::MODE_2V2,
                'stake' => $stake,
                'status' => TrucoMatch::STATUS_WAITING,
                'code' => $code,
                'host_user_id' => $host->id,
                'score_us' => 0,
                'score_them' => 0,
                'hand_value' => 1,
                'target_winner' => TrucoMatch::WINNER_THEM,
                'edge_roll' => 0,
                'house_edge' => $this->houseEdge(),
                'state' => ['phase' => 'lobby', 'message' => 'Aguardando parceiro…'],
            ]);

            // Seats: 0 bottom us, 1 left them, 2 top us partner, 3 right them
            $this->createSeat($match, $host->id, false, $host->name, 'us', 0);
            $this->createSeat($match, null, true, $this->ghostName(), 'them', 1);
            $this->createSeat($match, null, false, 'Parceiro', 'us', 2); // empty until join/fill
            $this->createSeat($match, null, true, $this->ghostName(), 'them', 3);

            return $match->fresh(['seats']);
        });
    }

    public function join2v2Room(User $user, string $code): TrucoMatch
    {
        $code = strtoupper(trim($code));

        return DB::transaction(function () use ($user, $code) {
            $match = TrucoMatch::query()
                ->where('code', $code)
                ->where('mode', TrucoMatch::MODE_2V2)
                ->where('status', TrucoMatch::STATUS_WAITING)
                ->lockForUpdate()
                ->first();

            if (! $match) {
                throw new RuntimeException('Sala não encontrada ou já iniciada.');
            }
            if ((int) $match->host_user_id === (int) $user->id) {
                throw new RuntimeException('Você já é o dono da sala.');
            }

            $partner = $match->seats()->where('seat_index', 2)->lockForUpdate()->firstOrFail();
            if ($partner->user_id) {
                throw new RuntimeException('Sala já tem parceiro.');
            }

            $partner->update([
                'user_id' => $user->id,
                'is_ghost' => false,
                'display_name' => $user->name,
            ]);

            $state = $match->state ?? [];
            $state['message'] = 'Parceiro entrou. Host pode iniciar.';
            $match->state = $state;
            $match->save();

            return $match->fresh(['seats']);
        });
    }

    public function start2v2(User $host, TrucoMatch $match): TrucoMatch
    {
        if ((int) $match->host_user_id !== (int) $host->id) {
            throw new RuntimeException('Só o host inicia a sala.');
        }
        if ($match->status !== TrucoMatch::STATUS_WAITING) {
            throw new RuntimeException('Sala já iniciada.');
        }

        return DB::transaction(function () use ($match) {
            $match = TrucoMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $match->load('seats');

            $partner = $match->seats->firstWhere('seat_index', 2);
            if ($partner && ! $partner->user_id) {
                $partner->update([
                    'is_ghost' => true,
                    'display_name' => $this->ghostName(),
                    'user_id' => null,
                ]);
            }

            $splits = [];
            foreach ($match->seats()->lockForUpdate()->get() as $seat) {
                if (! $seat->user_id || $seat->is_ghost) {
                    continue;
                }
                $locked = User::query()->whereKey($seat->user_id)->lockForUpdate()->firstOrFail();
                $split = $this->debitHuman($locked, (float) $match->stake);
                $seat->update([
                    'from_balance' => $split['from_balance'],
                    'from_bonus' => $split['from_bonus'],
                ]);
                $splits[$locked->id] = $split;
                $this->recordEntry(
                    $match,
                    (float) $match->stake,
                    \App\Models\FinanceEntry::TYPE_TRUCO_ENTRY,
                    \App\Models\FinanceEntry::DIR_IN,
                    'Truco 2v2 entry user #'.$locked->id
                );
            }

            if ($splits === []) {
                throw new RuntimeException('Nenhum jogador para debitar.');
            }

            [$target, $roll, $edge] = $this->rollTarget();
            $hostSplit = $splits[$match->host_user_id] ?? ['from_balance' => 0, 'from_bonus' => 0];

            $match->fill([
                'status' => TrucoMatch::STATUS_PLAYING,
                'target_winner' => $target,
                'edge_roll' => $roll,
                'house_edge' => $edge,
                'from_balance' => $hostSplit['from_balance'],
                'from_bonus' => $hostSplit['from_bonus'],
                'stake_splits' => $splits,
                'hand_value' => 1,
                'score_us' => 0,
                'score_them' => 0,
            ]);
            $match->state = $this->newHandState($match, true);
            $this->bumpDeadline($match);
            $match->save();

            return $match->fresh(['seats']);
        });
    }

    public function forfeit(User $user, TrucoMatch $match): TrucoMatch
    {
        return DB::transaction(function () use ($user, $match) {
            $match = TrucoMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->assertSeated($match, $user);

            if ($match->status === TrucoMatch::STATUS_WAITING) {
                if ((int) $match->host_user_id === (int) $user->id) {
                    $match->status = TrucoMatch::STATUS_CANCELLED;
                    $match->save();

                    return $match->fresh(['seats']);
                }
                $match->seats()->where('user_id', $user->id)->update([
                    'user_id' => null,
                    'is_ghost' => false,
                    'display_name' => 'Parceiro',
                ]);

                return $match->fresh(['seats']);
            }

            if ($match->status !== TrucoMatch::STATUS_PLAYING) {
                throw new RuntimeException('Partida já encerrada.');
            }

            $state = $match->state ?? [];
            $this->settleMatch($match, $state, TrucoMatch::WINNER_THEM, 'Você saiu da partida.');

            return $match->fresh(['seats']);
        });
    }

    public function publicState(TrucoMatch $match, User $viewer): array
    {
        $this->ensureTurnNotExpired($match);
        $match->refresh()->loadMissing('seats');

        $state = $match->state ?? [];
        $escuro = (bool) ($state['escuro'] ?? false);
        $mySeat = $match->seats->first(fn (TrucoSeat $s) => (int) $s->user_id === (int) $viewer->id);
        $myIndex = $mySeat?->seat_index;
        $actions = $this->availableActions($match, $viewer);

        $rawHand = [];
        if ($match->mode === TrucoMatch::MODE_1V1) {
            if ($mySeat && $mySeat->team === 'us') {
                $rawHand = array_values($state['hand_us'] ?? []);
            }
        } else {
            $hands = $state['hands'] ?? [];
            $rawHand = $myIndex !== null
                ? array_values($hands[(string) $myIndex] ?? $hands[$myIndex] ?? [])
                : [];
        }

        $myHand = [];
        foreach ($rawHand as $i => $code) {
            if ($escuro) {
                $myHand[] = [
                    'code' => (string) $i,
                    'rank' => '?',
                    'suit' => 'clubs',
                    'hidden' => true,
                ];
            } else {
                $myHand[] = $this->presentCard($code);
            }
        }

        $handCounts = [];
        if ($match->mode === TrucoMatch::MODE_1V1) {
            $handCounts = [
                0 => count($state['hand_us'] ?? []),
                1 => count($state['hand_them'] ?? []),
            ];
        } else {
            foreach ([0, 1, 2, 3] as $i) {
                $h = $state['hands'][(string) $i] ?? $state['hands'][$i] ?? [];
                $handCounts[$i] = count($h);
            }
        }

        $phase = $state['phase'] ?? 'play';
        $uiPhase = match ($phase) {
            'truco_pending' => 'waiting_raise',
            'mao_11_decision' => 'mao_11',
            'finished' => 'finished',
            default => 'play',
        };

        $pending = $state['pending_truco'] ?? null;
        $pendingRaise = is_array($pending) ? (int) ($pending['value'] ?? 0) : null;
        $yourTurn = in_array($phase, ['play', 'truco_pending', 'mao_11_decision'], true)
            && (
                in_array('play', $actions, true)
                || in_array('accept', $actions, true)
                || in_array('run', $actions, true)
                || in_array('truco', $actions, true)
                || in_array('raise', $actions, true)
                || in_array('mao11_play', $actions, true)
                || in_array('mao11_run', $actions, true)
            );

        $table = [];
        foreach ($state['table'] ?? [] as $play) {
            $table[] = [
                'team' => $play['team'] ?? null,
                'seat' => $play['seat'] ?? null,
                'card' => $this->presentCard($play['card'] ?? null),
            ];
        }

        return [
            'id' => $match->id,
            'match_id' => $match->id,
            'mode' => $match->mode,
            'code' => $match->code,
            'stake' => (float) $match->stake,
            'status' => $match->status,
            'score_us' => (int) $match->score_us,
            'score_them' => (int) $match->score_them,
            'hand_value' => (int) $match->hand_value,
            'phase' => $uiPhase,
            'turn' => $state['turn'] ?? 'us',
            'turn_seat' => $state['turn_seat'] ?? ($match->mode === TrucoMatch::MODE_1V1
                ? (($state['turn'] ?? 'us') === 'us' ? 0 : 1)
                : null),
            'vira' => $this->presentCard($state['vira'] ?? null),
            'manilhas' => array_values(array_filter(array_map(
                fn ($c) => $this->presentCard($c),
                $state['manilhas'] ?? []
            ))),
            'table' => $table,
            'tricks' => [
                'us' => (int) ($state['tricks_us'] ?? 0),
                'them' => (int) ($state['tricks_them'] ?? 0),
            ],
            'tricks_us' => (int) ($state['tricks_us'] ?? 0),
            'tricks_them' => (int) ($state['tricks_them'] ?? 0),
            'escuro' => $escuro,
            'mao_11' => (bool) ($state['mao_11'] ?? false) || $phase === 'mao_11_decision',
            'mao_de_onze' => $phase === 'mao_11_decision' || (bool) ($state['mao_11'] ?? false),
            'pending_truco' => $pending,
            'pending_raise' => $pendingRaise ?: null,
            'last_raise' => $pendingRaise ?: null,
            'message' => $state['message'] ?? null,
            'winner' => $state['match_winner'] ?? null,
            'payout' => isset($state['payout']) ? (float) $state['payout'] : null,
            'hand' => $myHand,
            'hand_counts' => $handCounts,
            'hand_count_them' => $handCounts[1] ?? 0,
            'my_seat' => $myIndex,
            'your_turn' => $yourTurn,
            'turn_deadline' => $match->turn_deadline?->toIso8601String(),
            'turn_seconds_left' => $match->turn_deadline
                ? max(0, $match->turn_deadline->getTimestamp() - time())
                : null,
            'turn_timeout_seconds' => $this->turnTimeoutSeconds(),
            'forfeit_reason' => $state['forfeit_reason'] ?? null,
            'reactions' => $state['reactions'] ?? [],
            'seats' => $match->seats->sortBy('seat_index')->values()->map(fn (TrucoSeat $s) => [
                'seat_index' => $s->seat_index,
                'team' => $s->team,
                'name' => $s->display_name,
                'is_ghost' => (bool) $s->is_ghost,
                'is_you' => (int) $s->user_id === (int) $viewer->id,
                'filled' => $s->user_id !== null || $s->is_ghost,
            ]),
            'actions' => $actions,
            'is_host' => (int) $match->host_user_id === (int) $viewer->id,
            'can_start' => $match->status === TrucoMatch::STATUS_WAITING
                && (int) $match->host_user_id === (int) $viewer->id,
        ];
    }

    public function act(TrucoMatch $match, User $user, string $action, ?string $card = null): TrucoMatch
    {
        return DB::transaction(function () use ($match, $user, $action, $card) {
            $match = TrucoMatch::query()->whereKey($match->id)->lockForUpdate()->firstOrFail();
            $this->ensureTurnNotExpired($match);
            $match->refresh();

            if ($action === 'react') {
                return $this->react($match, $user, $card ?? '👍');
            }

            if ($match->status !== TrucoMatch::STATUS_PLAYING) {
                throw new RuntimeException('Partida não está em andamento.');
            }

            $seat = $this->assertSeated($match, $user);
            $state = $match->state ?? [];

            if ($match->mode === TrucoMatch::MODE_1V1) {
                if ($seat->team !== 'us') {
                    throw new RuntimeException('Ação inválida.');
                }
                match ($action) {
                    'play' => $this->playCard1v1($match, $state, 'us', $card),
                    'truco', 'raise' => $this->requestRaise($match, $state, 'us'),
                    'accept' => $this->respondRaise($match, $state, 'us', true),
                    'run' => $this->respondRaise($match, $state, 'us', false),
                    'mao11_play' => $this->mao11Decision($match, $state, true),
                    'mao11_run' => $this->mao11Decision($match, $state, false),
                    default => throw new RuntimeException('Ação inválida.'),
                };
                $this->ghostTick1v1($match);
            } else {
                match ($action) {
                    'play' => $this->playCard2v2($match, $state, (int) $seat->seat_index, $card),
                    'truco', 'raise' => $this->requestRaise($match, $state, $seat->team),
                    'accept' => $this->respondRaise($match, $state, $seat->team, true),
                    'run' => $this->respondRaise($match, $state, $seat->team, false),
                    'mao11_play' => $this->mao11Decision($match, $state, true),
                    'mao11_run' => $this->mao11Decision($match, $state, false),
                    default => throw new RuntimeException('Ação inválida.'),
                };
                $this->ghostTick2v2($match);
            }

            $match->refresh();
            $this->bumpDeadline($match);
            $match->save();

            return $match->fresh(['seats']);
        });
    }

    public function ensureTurnNotExpired(TrucoMatch $match): void
    {
        if ($match->status !== TrucoMatch::STATUS_PLAYING || ! $match->turn_deadline) {
            return;
        }
        if ($match->turn_deadline->isFuture()) {
            return;
        }

        DB::transaction(function () use ($match) {
            $match = TrucoMatch::query()->whereKey($match->id)->lockForUpdate()->first();
            if (! $match || $match->status !== TrucoMatch::STATUS_PLAYING) {
                return;
            }
            if (! $match->turn_deadline || $match->turn_deadline->isFuture()) {
                return;
            }

            $state = $match->state ?? [];
            $phase = $state['phase'] ?? 'play';

            if ($phase === 'truco_pending') {
                $to = $state['pending_truco']['to'] ?? 'us';
                $this->respondRaise($match, $state, $to, false);

                return;
            }

            // Timeout = house wins (inatividade)
            $state['forfeit_reason'] = 'inactivity';
            $this->settleMatch($match, $state, TrucoMatch::WINNER_THEM, 'Você perdeu por inatividade.');
        });
    }

    /** @return list<string> */
    protected function availableActions(TrucoMatch $match, User $viewer): array
    {
        if ($match->status === TrucoMatch::STATUS_WAITING) {
            return (int) $match->host_user_id === (int) $viewer->id ? ['start_room'] : [];
        }
        if ($match->status !== TrucoMatch::STATUS_PLAYING) {
            return [];
        }

        $seat = $match->seats->first(fn (TrucoSeat $s) => (int) $s->user_id === (int) $viewer->id);
        if (! $seat) {
            return [];
        }

        $state = $match->state ?? [];
        $phase = $state['phase'] ?? 'play';

        if ($phase === 'mao_11_decision' && $seat->team === 'us') {
            return ['mao11_play', 'mao11_run'];
        }

        if ($phase === 'truco_pending' && ($state['pending_truco']['to'] ?? null) === $seat->team) {
            $actions = ['accept', 'run'];
            if ($this->nextHandValue((int) ($state['pending_truco']['value'] ?? 3)) !== null) {
                $actions[] = 'raise';
            }

            return $actions;
        }

        if ($phase !== 'play') {
            return [];
        }

        if ($match->mode === TrucoMatch::MODE_1V1) {
            if (($state['turn'] ?? '') !== $seat->team) {
                return [];
            }
        } else {
            if ((int) ($state['turn_seat'] ?? -1) !== (int) $seat->seat_index) {
                return [];
            }
        }

        $actions = ['play'];
        if (! ($state['escuro'] ?? false) && ! ($state['mao_11'] ?? false) && (int) $match->hand_value < 12) {
            $actions[] = 'truco';
        }

        return $actions;
    }

    protected function react(TrucoMatch $match, User $user, string $emoji): TrucoMatch
    {
        $seat = $this->assertSeated($match, $user);
        $state = $match->state ?? [];
        $reactions = $state['reactions'] ?? [];
        $reactions[(string) $seat->seat_index] = [
            'emoji' => mb_substr($emoji, 0, 8),
            'at' => now()->timestamp,
        ];
        $state['reactions'] = $reactions;
        $match->state = $state;
        $match->save();

        return $match->fresh(['seats']);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function playCard1v1(TrucoMatch $match, array &$state, string $team, ?string $card): void
    {
        if (($state['phase'] ?? '') !== 'play' || ($state['turn'] ?? '') !== $team) {
            throw new RuntimeException('Não é a sua vez.');
        }

        $handKey = $team === 'us' ? 'hand_us' : 'hand_them';
        $hand = array_values($state[$handKey] ?? []);
        [$played, $hand] = $this->takeCard($hand, $card, (bool) ($state['escuro'] ?? false));
        $state[$handKey] = $hand;
        $state['table'][] = ['team' => $team, 'seat' => $team === 'us' ? 0 : 1, 'card' => $played];
        $state['message'] = null;

        if (count($state['table']) >= 2) {
            $this->resolveTrick1v1($match, $state);
        } else {
            $state['turn'] = $team === 'us' ? 'them' : 'us';
            $match->state = $state;
            $match->save();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function playCard2v2(TrucoMatch $match, array &$state, int $seatIndex, ?string $card): void
    {
        if (($state['phase'] ?? '') !== 'play' || (int) ($state['turn_seat'] ?? -1) !== $seatIndex) {
            throw new RuntimeException('Não é a sua vez.');
        }

        $hands = $state['hands'] ?? [];
        $key = (string) $seatIndex;
        $hand = array_values($hands[$key] ?? []);
        [$played, $hand] = $this->takeCard($hand, $card, (bool) ($state['escuro'] ?? false));
        $hands[$key] = $hand;
        $state['hands'] = $hands;

        $team = in_array($seatIndex, [0, 2], true) ? 'us' : 'them';
        $state['table'][] = ['team' => $team, 'seat' => $seatIndex, 'card' => $played];
        $state['message'] = null;

        if (count($state['table']) >= 4) {
            $this->resolveTrick2v2($match, $state);
        } else {
            $state['turn_seat'] = ($seatIndex + 1) % 4;
            $state['turn'] = in_array($state['turn_seat'], [0, 2], true) ? 'us' : 'them';
            $match->state = $state;
            $match->save();
        }
    }

    /**
     * @param  list<string>  $hand
     * @return array{0: string, 1: list<string>}
     */
    protected function takeCard(array $hand, ?string $card, bool $escuro): array
    {
        if ($hand === []) {
            throw new RuntimeException('Sem cartas.');
        }
        if ($escuro) {
            $idx = is_numeric($card) ? (int) $card : 0;
            if (! isset($hand[$idx])) {
                throw new RuntimeException('Carta inválida.');
            }
            $played = $hand[$idx];
            array_splice($hand, $idx, 1);

            return [$played, array_values($hand)];
        }
        if ($card === null || ! in_array($card, $hand, true)) {
            throw new RuntimeException('Carta inválida.');
        }

        return [$card, array_values(array_filter($hand, fn ($c) => $c !== $card))];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function resolveTrick1v1(TrucoMatch $match, array &$state): void
    {
        $a = $state['table'][0];
        $b = $state['table'][1];
        $cmp = $this->compareCards($a['card'], $b['card'], $state['manilha_rank']);
        $winner = $cmp >= 0 ? $a['team'] : $b['team'];

        if ($winner === 'us') {
            $state['tricks_us'] = (int) $state['tricks_us'] + 1;
        } else {
            $state['tricks_them'] = (int) $state['tricks_them'] + 1;
        }
        $state['last_trick_winner'] = $winner;
        $state['table'] = [];
        $state['turn'] = $winner;

        $us = (int) $state['tricks_us'];
        $them = (int) $state['tricks_them'];
        if ($us >= 2 || $them >= 2 || ($us + $them) >= 3) {
            $this->finishHand($match, $state, $us > $them ? 'us' : ($them > $us ? 'them' : $winner));
        } else {
            $match->state = $state;
            $match->save();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function resolveTrick2v2(TrucoMatch $match, array &$state): void
    {
        $best = $state['table'][0];
        foreach (array_slice($state['table'], 1) as $play) {
            if ($this->compareCards($play['card'], $best['card'], $state['manilha_rank']) > 0) {
                $best = $play;
            }
        }
        $winner = $best['team'];
        if ($winner === 'us') {
            $state['tricks_us'] = (int) $state['tricks_us'] + 1;
        } else {
            $state['tricks_them'] = (int) $state['tricks_them'] + 1;
        }
        $state['last_trick_winner'] = $winner;
        $state['table'] = [];
        $state['turn_seat'] = (int) $best['seat'];
        $state['turn'] = $winner;

        $us = (int) $state['tricks_us'];
        $them = (int) $state['tricks_them'];
        if ($us >= 2 || $them >= 2) {
            $this->finishHand($match, $state, $us > $them ? 'us' : 'them');
        } else {
            $match->state = $state;
            $match->save();
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function finishHand(TrucoMatch $match, array &$state, string $handWinner): void
    {
        $value = (int) $match->hand_value;
        if ($handWinner === 'us') {
            $match->score_us = min(12, (int) $match->score_us + $value);
        } else {
            $match->score_them = min(12, (int) $match->score_them + $value);
        }

        if ($match->score_us >= 12 || $match->score_them >= 12) {
            $this->settleMatch($match, $state, $match->target_winner);

            return;
        }

        $match->hand_value = 1;
        $match->state = $this->newHandState($match, false);
        $this->bumpDeadline($match);
        $match->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function settleMatch(TrucoMatch $match, array &$state, string $winner, ?string $message = null): void
    {
        if ($match->settled_at) {
            return;
        }

        $payoutEach = 0.0;
        if ($winner === TrucoMatch::WINNER_US) {
            $payoutEach = round((float) $match->stake * 2, 2);
            $match->loadMissing('seats');
            foreach ($match->seats as $seat) {
                if (! $seat->user_id || $seat->is_ghost || $seat->team !== 'us') {
                    continue;
                }
                $user = User::query()->whereKey($seat->user_id)->lockForUpdate()->first();
                if (! $user) {
                    continue;
                }
                $split = [
                    'from_balance' => (float) ($seat->from_balance ?? 0),
                    'from_bonus' => (float) ($seat->from_bonus ?? 0),
                ];
                if ($split['from_balance'] + $split['from_bonus'] <= 0) {
                    $splits = $match->stake_splits ?? [];
                    $split = $splits[$user->id] ?? [
                        'from_balance' => (float) $match->from_balance,
                        'from_bonus' => (float) $match->from_bonus,
                    ];
                }
                $this->wallet->creditCashout($user, (float) $match->stake, $payoutEach, $split);
                $this->recordEntry(
                    $match,
                    $payoutEach,
                    \App\Models\FinanceEntry::TYPE_TRUCO_PAYOUT,
                    \App\Models\FinanceEntry::DIR_OUT,
                    'Truco payout user #'.$user->id
                );
            }
        } else {
            $this->recordEntry(
                $match,
                (float) $match->stake,
                \App\Models\FinanceEntry::TYPE_TRUCO_HOUSE_RESULT,
                \App\Models\FinanceEntry::DIR_IN,
                'target='.$winner.' roll='.(string) $match->edge_roll
            );
        }

        $state['phase'] = 'finished';
        $state['match_winner'] = $winner;
        $state['payout'] = $payoutEach;
        $state['message'] = $message ?? ($winner === TrucoMatch::WINNER_US
            ? 'Vitória! +$ '.number_format($payoutEach, 2, '.', ',')
            : 'Derrota nesta partida.');

        $match->status = TrucoMatch::STATUS_FINISHED;
        $match->settled_at = now();
        $match->turn_deadline = null;
        $match->state = $state;
        if ($winner === TrucoMatch::WINNER_US) {
            $match->score_us = max((int) $match->score_us, 12);
        } else {
            $match->score_them = max((int) $match->score_them, 12);
        }
        $match->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function requestRaise(TrucoMatch $match, array &$state, string $from): void
    {
        if (($state['phase'] ?? '') === 'truco_pending' && ($state['pending_truco']['to'] ?? null) === $from) {
            $next = $this->nextHandValue((int) ($state['pending_truco']['value'] ?? 3));
            if ($next === null) {
                throw new RuntimeException('Não dá para aumentar mais.');
            }
            $state['pending_truco'] = [
                'from' => $from,
                'to' => $from === 'us' ? 'them' : 'us',
                'value' => $next,
            ];
            $state['message'] = $this->raiseLabel($next).'!';
            $match->state = $state;
            $match->save();

            return;
        }

        if (($state['phase'] ?? '') !== 'play') {
            throw new RuntimeException('Não pode trucar agora.');
        }
        if ($state['escuro'] ?? false) {
            throw new RuntimeException('Sem truco no escuro.');
        }

        $next = $this->nextHandValue((int) $match->hand_value);
        if ($next === null) {
            throw new RuntimeException('Mão já vale o máximo.');
        }

        $state['phase'] = 'truco_pending';
        $state['pending_truco'] = [
            'from' => $from,
            'to' => $from === 'us' ? 'them' : 'us',
            'value' => $next,
        ];
        $state['message'] = $this->raiseLabel($next).'!';
        $match->state = $state;
        $match->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function respondRaise(TrucoMatch $match, array &$state, string $team, bool $accept): void
    {
        if (($state['phase'] ?? '') !== 'truco_pending') {
            throw new RuntimeException('Não há truco pendente.');
        }
        if (($state['pending_truco']['to'] ?? null) !== $team) {
            throw new RuntimeException('Não é você quem responde.');
        }

        $value = (int) $state['pending_truco']['value'];
        $from = $state['pending_truco']['from'];

        if (! $accept) {
            $state['pending_truco'] = null;
            $state['phase'] = 'play';
            $this->finishHand($match, $state, $from);

            return;
        }

        $match->hand_value = $value;
        $state['pending_truco'] = null;
        $state['phase'] = 'play';
        $state['message'] = 'Aceito! Vale '.$value;
        $match->state = $state;
        $match->save();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function mao11Decision(TrucoMatch $match, array &$state, bool $play): void
    {
        if (($state['phase'] ?? '') !== 'mao_11_decision') {
            throw new RuntimeException('Não é mão de 11.');
        }

        if (! $play) {
            $match->score_them = min(12, (int) $match->score_them + 1);
            if ($match->score_them >= 12 || $match->score_us >= 12) {
                $this->settleMatch($match, $state, $match->target_winner);

                return;
            }
            $match->hand_value = 1;
            $match->state = $this->newHandState($match, false);
            $this->bumpDeadline($match);
            $match->save();

            return;
        }

        $match->hand_value = 3;
        $state['mao_11'] = true;
        $state['phase'] = 'play';
        $state['message'] = 'Mão de 11 — vale 3';
        $match->state = $state;
        $match->save();
    }

    protected function ghostTick1v1(TrucoMatch $match): void
    {
        $match->refresh();
        if ($match->status !== TrucoMatch::STATUS_PLAYING) {
            return;
        }
        $state = $match->state ?? [];
        $phase = $state['phase'] ?? 'play';

        if ($phase === 'truco_pending' && ($state['pending_truco']['to'] ?? null) === 'them') {
            $wantWin = $match->target_winner === TrucoMatch::WINNER_THEM;
            $accept = $wantWin ? mt_rand(1, 100) <= 70 : mt_rand(1, 100) <= 45;
            if ($accept && $this->nextHandValue((int) $state['pending_truco']['value']) !== null && mt_rand(1, 100) <= 20) {
                $this->requestRaise($match, $state, 'them');

                return;
            }
            $this->respondRaise($match, $state, 'them', $accept);
            $this->ghostTick1v1($match->fresh());

            return;
        }

        if ($phase === 'play' && ($state['turn'] ?? '') === 'them') {
            if (! ($state['escuro'] ?? false) && (int) $match->hand_value < 12 && mt_rand(1, 100) <= 15) {
                try {
                    $this->requestRaise($match, $state, 'them');

                    return;
                } catch (RuntimeException) {
                }
            }
            $hand = array_values($state['hand_them'] ?? []);
            if ($hand === []) {
                return;
            }
            $card = ($state['escuro'] ?? false)
                ? (string) array_rand($hand)
                : $hand[array_rand($hand)];
            $this->playCard1v1($match, $state, 'them', $card);
            $match->refresh();
            if (($match->state['turn'] ?? '') === 'them' && ($match->state['phase'] ?? '') === 'play') {
                $this->ghostTick1v1($match);
            }
        }
    }

    protected function ghostTick2v2(TrucoMatch $match): void
    {
        $match->refresh()->loadMissing('seats');
        if ($match->status !== TrucoMatch::STATUS_PLAYING) {
            return;
        }
        $state = $match->state ?? [];
        $phase = $state['phase'] ?? 'play';

        if ($phase === 'truco_pending' && ($state['pending_truco']['to'] ?? null) === 'them') {
            $wantWin = $match->target_winner === TrucoMatch::WINNER_THEM;
            $accept = $wantWin ? mt_rand(1, 100) <= 70 : mt_rand(1, 100) <= 45;
            $this->respondRaise($match, $state, 'them', $accept);
            $this->ghostTick2v2($match->fresh());

            return;
        }

        if ($phase !== 'play') {
            return;
        }

        $turnSeat = (int) ($state['turn_seat'] ?? 0);
        $seat = $match->seats->firstWhere('seat_index', $turnSeat);
        if (! $seat || ! $seat->is_ghost) {
            return;
        }

        $hands = $state['hands'] ?? [];
        $key = (string) $turnSeat;
        $hand = array_values($hands[$key] ?? []);
        if ($hand === []) {
            return;
        }
        if (! ($state['escuro'] ?? false) && (int) $match->hand_value < 12 && mt_rand(1, 100) <= 12) {
            try {
                $this->requestRaise($match, $state, $seat->team);

                return;
            } catch (RuntimeException) {
            }
        }
        $card = ($state['escuro'] ?? false)
            ? (string) array_rand($hand)
            : $hand[array_rand($hand)];
        $this->playCard2v2($match, $state, $turnSeat, $card);
        $match->refresh();
        $this->ghostTick2v2($match);
    }

    /** @return array<string, mixed> */
    protected function newHandState(TrucoMatch $match, bool $first): array
    {
        $deck = $this->buildDeck();
        shuffle($deck);
        $vira = array_shift($deck);
        $manilhaRank = $this->nextRank($this->rankOf($vira));
        $manilhas = [];
        foreach (self::SUITS as $suit) {
            $manilhas[] = $manilhaRank.$suit;
        }

        $scoreUs = (int) $match->score_us;
        $scoreThem = (int) $match->score_them;
        $escuro = $scoreUs === 11 && $scoreThem === 11;
        $mao11Us = $scoreUs === 11 && $scoreThem < 11;
        $mao11Them = $scoreThem === 11 && $scoreUs < 11;

        if ($escuro) {
            $match->hand_value = 3;
        }

        // Rival em 11: fantasma decide jogar (vale 3) ou correr (cede 1).
        if ($mao11Them && ! $escuro) {
            $wantWin = $match->target_winner === TrucoMatch::WINNER_THEM;
            if ($wantWin || mt_rand(1, 100) <= 55) {
                $match->hand_value = 3;
                $mao11Us = false;
                $phase = 'play';
                $mao11Msg = 'Rival jogou a mão de 11 (vale 3)';
            } else {
                $match->score_us = min(12, $scoreUs + 1);
                if ((int) $match->score_us >= 12) {
                    $done = [
                        'phase' => 'finished',
                        'vira' => $vira,
                        'manilha_rank' => $manilhaRank,
                        'manilhas' => $manilhas,
                        'table' => [],
                        'tricks_us' => 0,
                        'tricks_them' => 0,
                        'escuro' => false,
                        'mao_11' => false,
                        'pending_truco' => null,
                        'reactions' => [],
                        'message' => 'Rival correu na mão de 11.',
                    ];
                    $this->settleMatch($match, $done, $match->target_winner, 'Rival correu na mão de 11.');

                    return $match->state ?? $done;
                }

                return $this->newHandState($match, false);
            }
        } else {
            $phase = $mao11Us ? 'mao_11_decision' : 'play';
            $mao11Msg = null;
        }

        $base = [
            'phase' => $phase,
            'vira' => $vira,
            'manilha_rank' => $manilhaRank,
            'manilhas' => $manilhas,
            'table' => [],
            'tricks_us' => 0,
            'tricks_them' => 0,
            'escuro' => $escuro,
            'mao_11' => $escuro || ((int) $match->hand_value >= 3 && ($mao11Us || $mao11Them)),
            'pending_truco' => null,
            'reactions' => [],
            'message' => $escuro
                ? 'MÃO NO ESCURO'
                : ($mao11Msg
                    ?? ($phase === 'mao_11_decision'
                        ? 'Mão de 11 — jogar ou correr?'
                        : ($first ? 'Boa sorte!' : 'Nova mão'))),
        ];

        if ($match->mode === TrucoMatch::MODE_1V1) {
            $handUs = array_splice($deck, 0, 3);
            $handThem = array_splice($deck, 0, 3);
            if ($match->target_winner === TrucoMatch::WINNER_THEM && mt_rand(1, 100) <= 55) {
                [$handUs, $handThem] = [$handThem, $handUs];
            }

            return $base + [
                'turn' => 'us',
                'hand_us' => $handUs,
                'hand_them' => $handThem,
            ];
        }

        $hands = [
            '0' => array_splice($deck, 0, 3),
            '1' => array_splice($deck, 0, 3),
            '2' => array_splice($deck, 0, 3),
            '3' => array_splice($deck, 0, 3),
        ];
        if ($match->target_winner === TrucoMatch::WINNER_THEM && mt_rand(1, 100) <= 50) {
            [$hands['0'], $hands['1']] = [$hands['1'], $hands['0']];
            [$hands['2'], $hands['3']] = [$hands['3'], $hands['2']];
        }

        return $base + [
            'turn' => 'us',
            'turn_seat' => 0,
            'hands' => $hands,
        ];
    }

    protected function bumpDeadline(TrucoMatch $match): void
    {
        if ($match->status !== TrucoMatch::STATUS_PLAYING) {
            $match->turn_deadline = null;

            return;
        }
        $match->turn_deadline = now()->addSeconds($this->turnTimeoutSeconds());
    }

    /** @return array{0: string, 1: float, 2: float} */
    protected function rollTarget(): array
    {
        $edge = $this->houseEdge();
        if ((int) Setting::getValue('truco_fair_debug', 0) === 1) {
            $edge = 0.0;
        }
        $roll = mt_rand() / mt_getrandmax();
        $playerWinChance = max(0.01, min(0.99, 0.5 - ($edge / 2)));
        $target = $roll < $playerWinChance ? TrucoMatch::WINNER_US : TrucoMatch::WINNER_THEM;

        return [$target, $roll, $edge];
    }

    /** @return array{from_balance: float, from_bonus: float} */
    protected function debitHuman(User $user, float $stake): array
    {
        $split = $this->wallet->debit($user, $stake);
        $this->wagering->addProgress($user, $stake);

        return $split;
    }

    protected function recordEntry(TrucoMatch $match, float $amount, string $type, string $direction, ?string $note = null): void
    {
        if ($amount <= 0) {
            return;
        }
        $this->ledger->record($type, $direction, $amount, $match, null, $note);
    }

    protected function createSeat(
        TrucoMatch $match,
        ?int $userId,
        bool $ghost,
        string $name,
        string $team,
        int $index,
        ?array $split = null,
    ): TrucoSeat {
        return TrucoSeat::query()->create([
            'truco_match_id' => $match->id,
            'user_id' => $userId,
            'is_ghost' => $ghost,
            'display_name' => $name,
            'team' => $team,
            'seat_index' => $index,
            'from_balance' => $split['from_balance'] ?? null,
            'from_bonus' => $split['from_bonus'] ?? null,
        ]);
    }

    protected function assertSeated(TrucoMatch $match, User $user): TrucoSeat
    {
        $seat = $match->seats()->where('user_id', $user->id)->first();
        if (! $seat) {
            throw new RuntimeException('Você não está nesta mesa.');
        }

        return $seat;
    }

    protected function assertStake(float $stake): float
    {
        $stake = round($stake, 2);
        $allowed = array_map(static fn (int $s): float => (float) $s, self::STAKES);
        if (! in_array($stake, $allowed, true)) {
            throw new RuntimeException('Valor de entrada inválido.');
        }

        return $stake;
    }

    protected function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (TrucoMatch::query()->where('code', $code)->whereIn('status', [
            TrucoMatch::STATUS_WAITING,
            TrucoMatch::STATUS_PLAYING,
        ])->exists());

        return $code;
    }

    protected function ghostName(): string
    {
        return self::GHOST_NAMES[array_rand(self::GHOST_NAMES)];
    }

    /** @return list<string> */
    protected function buildDeck(): array
    {
        $deck = [];
        foreach (self::RANKS as $rank) {
            foreach (self::SUITS as $suit) {
                $deck[] = $rank.$suit;
            }
        }

        return $deck;
    }

    protected function rankOf(string $card): string
    {
        return substr($card, 0, -1);
    }

    protected function suitOf(string $card): string
    {
        return substr($card, -1);
    }

    protected function nextRank(string $rank): string
    {
        $i = array_search($rank, self::RANKS, true);

        return self::RANKS[(($i === false ? 0 : $i) + 1) % count(self::RANKS)];
    }

    protected function compareCards(string $a, string $b, string $manilhaRank): int
    {
        $sa = $this->cardStrength($a, $manilhaRank);
        $sb = $this->cardStrength($b, $manilhaRank);
        if ($sa === $sb) {
            return 0;
        }

        return $sa > $sb ? 1 : -1;
    }

    protected function cardStrength(string $card, string $manilhaRank): int
    {
        $rank = $this->rankOf($card);
        $suit = $this->suitOf($card);
        if ($rank === $manilhaRank) {
            $order = ['C' => 4, 'H' => 3, 'S' => 2, 'D' => 1];

            return 100 + ($order[$suit] ?? 0);
        }
        $i = array_search($rank, self::RANKS, true);

        return $i === false ? 0 : $i + 1;
    }

    protected function nextHandValue(int $current): ?int
    {
        return match ($current) {
            1 => 3,
            3 => 6,
            6 => 9,
            9 => 12,
            default => null,
        };
    }

    protected function raiseLabel(int $value): string
    {
        return match ($value) {
            3 => 'Truco',
            6 => 'Seis',
            9 => 'Nove',
            12 => 'Doze',
            default => (string) $value,
        };
    }

    /** @return array{code: string, rank: string, suit: string}|null */
    protected function presentCard(?string $code): ?array
    {
        if ($code === null || $code === '' || $code === 'BACK') {
            return null;
        }

        $suitMap = [
            'C' => 'clubs',
            'H' => 'hearts',
            'S' => 'spades',
            'D' => 'diamonds',
        ];

        return [
            'code' => $code,
            'rank' => $this->rankOf($code),
            'suit' => $suitMap[$this->suitOf($code)] ?? 'clubs',
        ];
    }
}
