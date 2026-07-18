<?php

namespace App\Http\Controllers;

use App\Models\TrucoMatch;
use App\Services\TrucoEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TrucoController extends Controller
{
    public function start(Request $request, TrucoEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'stake' => ['required', 'numeric'],
            'mode' => ['required', 'in:1v1,2v2'],
        ]);

        try {
            $match = $data['mode'] === '2v2'
                ? $engine->create2v2Room($request->user(), (float) $data['stake'])
                : $engine->start1v1($request->user(), (float) $data['stake']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($engine->publicState($match, $request->user()));
    }

    public function join(Request $request, TrucoEngine $engine): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:12'],
        ]);

        try {
            $match = $engine->join2v2Room($request->user(), $data['code']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($engine->publicState($match, $request->user()));
    }

    public function startRoom(Request $request, TrucoMatch $match, TrucoEngine $engine): JsonResponse
    {
        try {
            $match = $engine->start2v2($request->user(), $match);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($engine->publicState($match, $request->user()));
    }

    public function state(Request $request, TrucoMatch $match, TrucoEngine $engine): JsonResponse
    {
        $this->authorizeSeated($match, $request);

        return response()->json($engine->publicState($match, $request->user()));
    }

    public function act(Request $request, TrucoMatch $match, TrucoEngine $engine): JsonResponse
    {
        $this->authorizeSeated($match, $request);

        $data = $request->validate([
            'action' => ['required', 'string'],
            'card' => ['nullable', 'string'],
            'emoji' => ['nullable', 'string', 'max:8'],
            'value' => ['nullable', 'integer'],
        ]);

        try {
            $payload = $data['card'] ?? $data['emoji'] ?? null;
            $match = $engine->act($match, $request->user(), $data['action'], $payload);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($engine->publicState($match, $request->user()));
    }

    public function leave(Request $request, TrucoMatch $match, TrucoEngine $engine): JsonResponse
    {
        try {
            $match = $engine->forfeit($request->user(), $match);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($engine->publicState($match, $request->user()));
    }

    protected function authorizeSeated(TrucoMatch $match, Request $request): void
    {
        $ok = $match->seats()->where('user_id', $request->user()->id)->exists()
            || (int) $match->host_user_id === (int) $request->user()->id;
        abort_unless($ok, 403);
    }
}
