<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class WithdrawalAdminController extends Controller
{
    public function index(): View
    {
        $withdrawals = Withdrawal::query()
            ->with('user')
            ->latest()
            ->paginate(40);

        return view('admin.withdrawals.index', compact('withdrawals'));
    }

    public function pay(Withdrawal $withdrawal, Request $request, WithdrawalPayoutService $payouts): RedirectResponse
    {
        try {
            $payouts->payViaPix($withdrawal, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return back()->withErrors(['withdrawal' => 'Falha ao pagar PIX: '.$e->getMessage()]);
        }

        return back()->with('status', 'PIX enviado via Asaas para o saque #'.$withdrawal->id.'.');
    }

    public function reject(Withdrawal $withdrawal, Request $request, WithdrawalPayoutService $payouts): RedirectResponse
    {
        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $withdrawal = $payouts->reject($withdrawal, $request->user(), $data['admin_note'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        return back()->with('status', $withdrawal->isAffiliateCommission()
            ? 'Saque #'.$withdrawal->id.' rejeitado. Depósitos liberados para novo cálculo.'
            : 'Saque #'.$withdrawal->id.' rejeitado e saldo devolvido ao jogador.');
    }
}
