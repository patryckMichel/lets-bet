<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\BonusCode;
use App\Models\User;
use App\Services\AdminLogger;
use App\Services\AffiliateCommissionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AffiliateAdminController extends Controller
{
    public function index(Request $request): View
    {
        $affiliates = Affiliate::query()
            ->with('user')
            ->withCount(['commissions', 'players'])
            ->orderByDesc('id')
            ->paginate(30);

        $bulkPreview = null;
        $from = $request->query('from');
        $to = $request->query('to');
        if ($request->boolean('calc_all') && $from && $to) {
            try {
                $bulkPreview = app(AffiliateCommissionService::class)->previewAll(
                    Carbon::parse($from),
                    Carbon::parse($to),
                );
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        return view('admin.affiliates.index', compact('affiliates', 'bulkPreview', 'from', 'to'));
    }

    public function show(Request $request, Affiliate $affiliate): View
    {
        $affiliate->load(['user', 'bonusCodes']);

        $players = User::query()
            ->where('affiliate_id', $affiliate->id)
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'players_page');

        $commissions = AffiliateCommission::query()
            ->where('affiliate_id', $affiliate->id)
            ->with(['deposit.user', 'withdrawal'])
            ->latest()
            ->paginate(20, ['*'], 'commissions_page');

        $preview = null;
        $from = $request->query('from');
        $to = $request->query('to');
        if ($request->boolean('calc') && $from && $to) {
            try {
                $preview = app(AffiliateCommissionService::class)->previewPeriod(
                    $affiliate,
                    Carbon::parse($from),
                    Carbon::parse($to),
                );
            } catch (\Throwable $e) {
                session()->flash('error', $e->getMessage());
            }
        }

        return view('admin.affiliates.show', compact(
            'affiliate',
            'commissions',
            'players',
            'preview',
            'from',
            'to',
        ));
    }

    public function update(Request $request, Affiliate $affiliate, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:15'],
            'referral_code' => [
                'required',
                'string',
                'max:40',
                Rule::unique('affiliates', 'referral_code')->ignore($affiliate->id),
            ],
            'pix_key' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'boolean'],
        ]);

        $before = $affiliate->only(['commission_percent', 'referral_code', 'pix_key', 'active']);
        $affiliate->fill([
            'commission_percent' => round((float) $data['commission_percent'], 2),
            'referral_code' => strtoupper(trim($data['referral_code'])),
            'pix_key' => filled($data['pix_key'] ?? null) ? trim($data['pix_key']) : null,
            'active' => $request->boolean('active'),
        ]);
        $affiliate->save();

        $logger->record(
            $request->user(),
            'affiliate.updated',
            $affiliate,
            $before,
            $affiliate->only(['commission_percent', 'referral_code', 'pix_key', 'active'])
        );

        return back()->with('status', 'Afiliado atualizado.');
    }

    public function calculate(Request $request, Affiliate $affiliate): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return redirect()->route('admin.affiliates.show', [
            'affiliate' => $affiliate,
            'calc' => 1,
            'from' => $data['from'],
            'to' => $data['to'],
        ]);
    }

    public function calculateAll(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return redirect()->route('admin.affiliates.index', [
            'calc_all' => 1,
            'from' => $data['from'],
            'to' => $data['to'],
        ]);
    }

    public function confirmAll(
        Request $request,
        AffiliateCommissionService $commissions,
        AdminLogger $logger,
    ): RedirectResponse {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $result = $commissions->confirmAll(
            Carbon::parse($data['from']),
            Carbon::parse($data['to']),
            $request->user(),
        );

        $logger->record($request->user(), 'affiliate.commission_confirmed_all', null, null, [
            'from' => $data['from'],
            'to' => $data['to'],
            'created' => $result['created'],
            'skipped_count' => count($result['skipped']),
        ]);

        $createdCount = count($result['created']);
        $totalAmount = round(array_sum(array_column($result['created'], 'amount')), 2);

        if ($createdCount === 0) {
            return redirect()
                ->route('admin.affiliates.index', [
                    'calc_all' => 1,
                    'from' => $data['from'],
                    'to' => $data['to'],
                ])
                ->withErrors(['commission' => 'Nenhum saque gerado. Verifique PIX e depósitos no período.']);
        }

        return redirect()
            ->route('admin.withdrawals.index')
            ->with('status', sprintf(
                '%d saque(s) de comissão gerados (R$ %s). %d afiliado(s) ignorados.',
                $createdCount,
                number_format($totalAmount, 2, ',', '.'),
                count($result['skipped'])
            ));
    }

    public function confirmCommission(
        Request $request,
        Affiliate $affiliate,
        AffiliateCommissionService $commissions,
        AdminLogger $logger,
    ): RedirectResponse {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        try {
            $withdrawal = $commissions->confirmPeriod(
                $affiliate,
                Carbon::parse($data['from']),
                Carbon::parse($data['to']),
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['commission' => $e->getMessage()]);
        }

        $logger->record($request->user(), 'affiliate.commission_confirmed', $affiliate, null, [
            'withdrawal_id' => $withdrawal->id,
            'amount' => $withdrawal->amount,
            'from' => $data['from'],
            'to' => $data['to'],
        ]);

        return redirect()
            ->route('admin.withdrawals.index')
            ->with('status', 'Comissão confirmada. Saque #'.$withdrawal->id.' criado em /admin/saques.');
    }

    public function storeCode(Request $request, Affiliate $affiliate, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:bonus_codes,code'],
            'type' => ['required', Rule::in([BonusCode::TYPE_FIXED, BonusCode::TYPE_MATCH])],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'match_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_bonus' => ['nullable', 'numeric', 'min:0'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $type = $data['type'];
        $bonusAmount = round((float) ($data['bonus_amount'] ?? 0), 2);
        $matchPercent = isset($data['match_percent']) ? round((float) $data['match_percent'], 2) : null;
        $maxBonus = isset($data['max_bonus']) && $data['max_bonus'] !== ''
            ? round((float) $data['max_bonus'], 2)
            : null;

        if ($type === BonusCode::TYPE_FIXED && $bonusAmount <= 0) {
            return back()->withInput()->withErrors(['bonus_amount' => 'Informe um valor de bônus maior que zero.']);
        }

        if ($type === BonusCode::TYPE_MATCH) {
            if ($matchPercent === null || $matchPercent <= 0) {
                return back()->withInput()->withErrors(['match_percent' => 'Informe o percentual de match (máx. 100).']);
            }
            if ($maxBonus === null || $maxBonus <= 0) {
                return back()->withInput()->withErrors(['max_bonus' => 'Informe o teto de bônus (obrigatório).']);
            }
            $bonusAmount = 0;
        } else {
            $matchPercent = null;
            $maxBonus = null;
        }

        $code = BonusCode::query()->create([
            'code' => strtoupper($data['code'] ?? Str::random(8)),
            'kind' => $type,
            'type' => $type,
            'affiliate_id' => $affiliate->id,
            'bonus_amount' => $bonusAmount,
            'match_percent' => $matchPercent,
            'max_bonus' => $maxBonus,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => true,
        ]);

        $logger->record($request->user(), 'bonus_code.created', $code, null, $code->toArray());

        return back()->with('status', 'Código criado: '.$code->code);
    }
}
