<?php

namespace App\Http\Controllers;

use App\Services\PlayerBonusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BonusController extends Controller
{
    public function create(): View
    {
        $user = auth()->user();

        return view('bonus.create', [
            'balance' => $user->total_balance,
            'bonusBalance' => round((float) $user->bonus_balance, 2),
        ]);
    }

    public function store(Request $request, PlayerBonusService $bonuses): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ], [
            'code.required' => 'Informe o código de bônus.',
        ]);

        try {
            $result = DB::transaction(function () use ($request, $data, $bonuses) {
                $user = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

                return $bonuses->redeemFixedCode($user, $data['code']);
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()
            ->route('bonus.create')
            ->with('status', 'Bônus de $ '.number_format($result['bonus'], 2, '.', ',').' creditado com o código '.$result['code'].'.');
    }
}
