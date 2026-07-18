<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceEntry;
use App\Services\AdminLogger;
use App\Services\FinanceLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FinanceAdminController extends Controller
{
    public function index(FinanceLedgerService $ledger): View
    {
        $entries = FinanceEntry::query()
            ->with('admin')
            ->latest()
            ->paginate(40);

        return view('admin.finance.index', [
            'entries' => $entries,
            'houseBalance' => $ledger->houseBalance(),
        ]);
    }

    public function store(Request $request, FinanceLedgerService $ledger, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:bank_transfer,manual_adjustment,affiliate_payout,withdrawal'],
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'note' => ['required', 'string', 'max:500'],
        ]);

        $entry = $ledger->record(
            $data['type'],
            $data['direction'],
            (float) $data['amount'],
            null,
            $request->user(),
            $data['note']
        );

        $logger->record($request->user(), 'finance.entry_created', $entry, null, $entry->toArray());

        return back()->with('status', 'Lançamento registrado.');
    }
}
