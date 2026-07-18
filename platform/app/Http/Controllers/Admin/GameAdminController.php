<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\AdminLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GameAdminController extends Controller
{
    public function index(): View
    {
        return view('admin.games.index', [
            'games' => Game::query()->ordered()->get(),
        ]);
    }

    public function updateStatus(Request $request, Game $game, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,coming_soon,maintenance'],
        ]);

        $before = $game->only(['status']);
        $game->status = $data['status'];
        $game->save();

        $logger->record($request->user(), 'game.status_updated', $game, $before, $game->only(['status']));

        return back()->with('status', "Status de {$game->name} atualizado.");
    }
}
