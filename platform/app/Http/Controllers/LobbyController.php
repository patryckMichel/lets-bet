<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\View\View;

class LobbyController extends Controller
{
    public function __invoke(): View
    {
        $games = Game::query()
            ->whereIn('slug', ['tigre-aviator', 'tigre-truco'])
            ->ordered()
            ->get();

        $featured = $games->firstWhere('slug', 'tigre-aviator') ?? $games->first();

        return view('lobby.index', [
            'game' => $featured,
            'games' => $games,
        ]);
    }
}
