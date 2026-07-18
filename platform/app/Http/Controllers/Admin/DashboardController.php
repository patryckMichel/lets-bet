<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliateCommission;
use App\Models\CrashBet;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use App\Services\FinanceLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(FinanceLedgerService $ledger): View
    {
        $since = now()->subDay();

        $activePlayers = User::query()
            ->where(function ($q) use ($since) {
                $q->where('last_login_at', '>=', $since)
                    ->orWhereIn('id', CrashBet::query()->where('created_at', '>=', $since)->select('user_id'));
            })
            ->count();

        $depositsTodayPaid = (float) Deposit::query()
            ->where('status', Deposit::STATUS_PAID)
            ->whereDate('paid_at', today())
            ->sum('amount');

        $depositsPending = Deposit::query()->where('status', Deposit::STATUS_PENDING)->count();

        $withdrawalsPending = Withdrawal::query()->where('status', Withdrawal::STATUS_PENDING)->count();
        $withdrawalsPendingAmount = (float) Withdrawal::query()
            ->where('status', Withdrawal::STATUS_PENDING)
            ->sum('amount');

        $betsStake = (float) CrashBet::query()->where('created_at', '>=', $since)->sum('amount');
        $betsPayout = (float) CrashBet::query()->where('created_at', '>=', $since)->sum('payout');
        $ggr = round($betsStake - $betsPayout, 2);

        $commissionsPending = (float) AffiliateCommission::query()
            ->where('status', AffiliateCommission::STATUS_OPEN)
            ->sum('commission_amount');

        $playerBalancesReal = (float) User::query()->sum('balance');
        $playerBalancesBonus = (float) User::query()->sum('bonus_balance');

        $chartLabels = [];
        $depositsSeries = [];
        $ggrSeries = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $chartLabels[] = $day->format('d/m');

            $depositsSeries[] = round((float) Deposit::query()
                ->where('status', Deposit::STATUS_PAID)
                ->whereDate('paid_at', $day)
                ->sum('amount'), 2);

            $stake = (float) CrashBet::query()->whereDate('created_at', $day)->sum('amount');
            $payout = (float) CrashBet::query()->whereDate('created_at', $day)->sum('payout');
            $ggrSeries[] = round($stake - $payout, 2);
        }

        $totalPlayers = User::query()->count();
        $totalAffiliates = User::query()->whereHas('affiliate')->count();

        return view('admin.dashboard', [
            'houseBalance' => $ledger->houseBalance(),
            'activePlayers' => $activePlayers,
            'totalPlayers' => $totalPlayers,
            'totalAffiliates' => $totalAffiliates,
            'depositsTodayPaid' => $depositsTodayPaid,
            'depositsPending' => $depositsPending,
            'withdrawalsPending' => $withdrawalsPending,
            'withdrawalsPendingAmount' => $withdrawalsPendingAmount,
            'ggr' => $ggr,
            'commissionsPending' => $commissionsPending,
            'playerBalances' => round($playerBalancesReal, 2),
            'playerBalancesBonus' => round($playerBalancesBonus, 2),
            'chartLabels' => $chartLabels,
            'depositsSeries' => $depositsSeries,
            'ggrSeries' => $ggrSeries,
            'recentWithdrawals' => Withdrawal::query()->with('user')->latest()->limit(6)->get(),
            'recentDeposits' => Deposit::query()->with('user')->latest()->limit(6)->get(),
        ]);
    }
}
