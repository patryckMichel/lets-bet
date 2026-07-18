<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\View\View;

class GameController extends Controller
{
    public function show(string $slug): View
    {
        $game = Game::query()->where('slug', $slug)->firstOrFail();

        return view('games.show', compact('game'));
    }
}
