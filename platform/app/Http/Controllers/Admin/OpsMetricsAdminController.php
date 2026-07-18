<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OpsEvent;
use App\Models\OpsHourlyStat;
use App\Models\PlayerSession;
use App\Models\User;
use App\Services\OpsMetricsService;
use Illuminate\View\View;

class OpsMetricsAdminController extends Controller
{
    public function index(OpsMetricsService $ops): View
    {
        $hourly = OpsHourlyStat::query()
            ->orderByDesc('hour_start')
            ->limit(48)
            ->get();

        $today = OpsHourlyStat::query()
            ->where('hour_start', '>=', now()->startOfDay())
            ->get();

        $recentEvents = OpsEvent::query()
            ->with('user:id,name,email')
            ->orderByDesc('occurred_at')
            ->limit(40)
            ->get();

        $openSessions = PlayerSession::query()
            ->with('user:id,name,email')
            ->whereNull('ended_at')
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->orderByDesc('last_seen_at')
            ->limit(30)
            ->get();

        return view('admin.ops.index', [
            'onlineNow' => $ops->onlineCount(),
            'hourly' => $hourly,
            'recentEvents' => $recentEvents,
            'openSessions' => $openSessions,
            'today' => [
                'logins' => (int) $today->sum('logins'),
                'unique_players' => (int) User::query()
                    ->where('last_seen_at', '>=', now()->startOfDay())
                    ->count(),
                'bets_count' => (int) $today->sum('bets_count'),
                'bets_amount' => (float) $today->sum('bets_amount'),
                'cashouts_amount' => (float) $today->sum('cashouts_amount'),
                'ggr' => (float) $today->sum('ggr'),
                'rounds_count' => (int) $today->sum('rounds_count'),
                'deposits_amount' => (float) $today->sum('deposits_amount'),
                'withdrawals_amount' => (float) $today->sum('withdrawals_amount'),
                'online_peak' => (int) $today->max('online_peak'),
            ],
        ]);
    }
}
