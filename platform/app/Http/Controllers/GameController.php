<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\TrucoEngine;
use Illuminate\View\View;

class GameController extends Controller
{
    public function show(string $slug, TrucoEngine $truco): View
    {
        $game = Game::query()->where('slug', $slug)->firstOrFail();

        if ($slug === 'tigre-truco') {
            return view('games.truco', [
                'game' => $game,
                'stakes' => $truco->stakes(),
                'houseEdge' => $truco->houseEdge(),
            ]);
        }

        return view('games.show', compact('game'));
    }
}
