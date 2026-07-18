<?php

namespace App\Http\Controllers;

use App\Services\CrashEngine;
use App\Services\OpsMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CrashController extends Controller
{
    public function __construct(private CrashEngine $engine) {}

    public function state(Request $request, OpsMetricsService $ops): JsonResponse
    {
        if ($request->user()) {
            $ops->heartbeat($request->user());
        }

        return response()->json($this->engine->getState($request->user()));
    }

    public function bet(Request $request): JsonResponse
    {
        if ($request->user()->is_blocked) {
            return response()->json(['message' => 'Conta bloqueada.'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'slot' => ['nullable', 'integer', 'in:1,2'],
            'auto_cashout_at' => ['nullable', 'numeric', 'min:1.01'],
        ]);

        try {
            $bet = $this->engine->placeBet(
                $request->user(),
                (float) $data['amount'],
                (int) ($data['slot'] ?? 1),
                isset($data['auto_cashout_at']) ? (float) $data['auto_cashout_at'] : null,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'bet' => [
                'id' => $bet->id,
                'slot' => $bet->slot,
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
            ],
            'state' => $this->engine->getState($request->user()),
        ]);
    }

    public function cashout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot' => ['nullable', 'integer', 'in:1,2'],
        ]);

        try {
            $bet = $this->engine->cashout(
                $request->user(),
                (int) ($data['slot'] ?? 1),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $user = $request->user()->fresh();

        return response()->json([
            'ok' => true,
            'bet' => [
                'id' => $bet->id,
                'slot' => $bet->slot,
                'amount' => (float) $bet->amount,
                'status' => $bet->status,
                'cashout_multiplier' => (float) $bet->cashout_multiplier,
                'payout' => (float) $bet->payout,
            ],
            'balance' => (float) $user->total_balance,
            'wallet' => [
                'balance' => (float) $user->balance,
                'bonus_balance' => (float) $user->bonus_balance,
                'total' => (float) $user->total_balance,
            ],
        ]);
    }
}
