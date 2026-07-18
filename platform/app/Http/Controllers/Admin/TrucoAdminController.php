<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TrucoMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrucoAdminController extends Controller
{
    public function index(): View
    {
        $total = TrucoMatch::query()->where('status', TrucoMatch::STATUS_FINISHED)->count();
        $winsUs = TrucoMatch::query()
            ->where('status', TrucoMatch::STATUS_FINISHED)
            ->where('target_winner', TrucoMatch::WINNER_US)
            ->count();
        $winsThem = TrucoMatch::query()
            ->where('status', TrucoMatch::STATUS_FINISHED)
            ->where('target_winner', TrucoMatch::WINNER_THEM)
            ->count();

        $volume = (float) TrucoMatch::query()
            ->where('status', TrucoMatch::STATUS_FINISHED)
            ->sum('stake');

        // Approx P&L: each them-win keeps stake * humans; each us-win pays stake per human.
        // Use stake_splits count when present.
        $finished = TrucoMatch::query()
            ->where('status', TrucoMatch::STATUS_FINISHED)
            ->get(['stake', 'target_winner', 'mode', 'stake_splits']);

        $housePnL = 0.0;
        foreach ($finished as $m) {
            $humans = is_array($m->stake_splits) ? max(1, count($m->stake_splits)) : 1;
            $stake = (float) $m->stake;
            if ($m->target_winner === TrucoMatch::WINNER_THEM) {
                $housePnL += $stake * $humans;
            } else {
                $housePnL -= $stake * $humans; // paid profit equal to stake each
            }
        }

        $byStake = TrucoMatch::query()
            ->where('status', TrucoMatch::STATUS_FINISHED)
            ->select('stake', DB::raw('count(*) as c'))
            ->groupBy('stake')
            ->orderBy('stake')
            ->get();

        $edge = (float) Setting::getValue('truco_house_edge', 0.05);
        $theoreticalWinrateHouse = 0.5 + $edge;

        return view('admin.truco.index', [
            'total' => $total,
            'winsUs' => $winsUs,
            'winsThem' => $winsThem,
            'volume' => $volume,
            'housePnL' => $housePnL,
            'byStake' => $byStake,
            'edge' => $edge,
            'theoreticalWinrateHouse' => $theoreticalWinrateHouse,
            'actualHouseWinrate' => $total > 0 ? $winsThem / $total : 0,
            'playing' => TrucoMatch::query()->where('status', TrucoMatch::STATUS_PLAYING)->count(),
            'waiting' => TrucoMatch::query()->where('status', TrucoMatch::STATUS_WAITING)->count(),
        ]);
    }
}
