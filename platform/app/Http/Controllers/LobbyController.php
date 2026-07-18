<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\View\View;

class LobbyController extends Controller
{
    public function __invoke(): View
    {
        $game = Game::query()
            ->where('slug', 'tigre-aviator')
            ->ordered()
            ->firstOrFail();

        return view('lobby.index', [
            'game' => $game,
        ]);
    }
}
