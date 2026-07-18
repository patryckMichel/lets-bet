<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusCode;
use App\Services\AdminLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BonusCodeAdminController extends Controller
{
    public function index(): View
    {
        $codes = BonusCode::query()->with('affiliate.user')->latest()->paginate(40);
        $helpCatalog = BonusCode::helpCatalog();

        return view('admin.bonus-codes.index', compact('codes', 'helpCatalog'));
    }

    public function store(Request $request, AdminLogger $logger): RedirectResponse
    {
        $kinds = array_keys(BonusCode::helpCatalog());

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:40', 'unique:bonus_codes,code'],
            'kind' => ['required', Rule::in($kinds)],
            'affiliate_id' => ['nullable', 'exists:affiliates,id'],
            'bonus_amount' => ['nullable', 'numeric', 'min:0'],
            'match_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_bonus' => ['nullable', 'numeric', 'min:0'],
            'inactive_days' => ['nullable', 'integer', 'min:1'],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $kind = $data['kind'];
        $bonusAmount = round((float) ($data['bonus_amount'] ?? 0), 2);
        $matchPercent = isset($data['match_percent']) && $data['match_percent'] !== ''
            ? round((float) $data['match_percent'], 2)
            : null;
        $maxBonus = isset($data['max_bonus']) && $data['max_bonus'] !== ''
            ? round((float) $data['max_bonus'], 2)
            : null;
        $inactiveDays = isset($data['inactive_days']) && $data['inactive_days'] !== ''
            ? (int) $data['inactive_days']
            : null;

        $error = $this->validateKindFields($kind, $bonusAmount, $matchPercent, $maxBonus, $inactiveDays);
        if ($error) {
            return back()->withInput()->withErrors($error);
        }

        if (in_array($kind, BonusCode::SYSTEM_KINDS, true)
            && BonusCode::hasActiveSystemCampaign($kind)) {
            return back()->withInput()->withErrors([
                'kind' => 'Já existe um bônus deste tipo ativo nesta vigência. Desative o atual antes de criar outro.',
            ]);
        }

        [$bonusAmount, $matchPercent, $maxBonus, $inactiveDays] = $this->normalizeFields(
            $kind,
            $bonusAmount,
            $matchPercent,
            $maxBonus,
            $inactiveDays
        );

        $codeValue = strtoupper($data['code'] ?? '');
        if ($codeValue === '') {
            $codeValue = match ($kind) {
                BonusCode::KIND_NEW_PLAYER => 'NOVOJOGADOR',
                BonusCode::KIND_AFFILIATE_SIGNUP => 'AFILICADASTRO',
                BonusCode::KIND_FIRST_DEPOSIT => 'PRIMEIRODEP',
                BonusCode::KIND_CASHBACK => 'CASHBACK',
                BonusCode::KIND_RELOAD => 'RECARGA',
                default => Str::upper(Str::random(8)),
            };
            if (BonusCode::query()->where('code', $codeValue)->exists()) {
                $codeValue = Str::upper(Str::random(10));
            }
        }

        $legacyType = in_array($kind, [BonusCode::KIND_FIXED, BonusCode::KIND_MATCH], true)
            ? $kind
            : BonusCode::KIND_FIXED;

        $code = BonusCode::query()->create([
            'code' => $codeValue,
            'kind' => $kind,
            'type' => $legacyType,
            'affiliate_id' => $data['affiliate_id'] ?? null,
            'bonus_amount' => $bonusAmount,
            'match_percent' => $matchPercent,
            'max_bonus' => $maxBonus,
            'inactive_days' => $inactiveDays,
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'active' => true,
        ]);

        $logger->record($request->user(), 'bonus_code.created', $code, null, $code->toArray());

        return back()->with('status', 'Campanha criada: '.$code->code.' ('.$code->kindLabel().')');
    }

    public function toggle(Request $request, BonusCode $bonusCode, AdminLogger $logger): RedirectResponse
    {
        $before = ['active' => $bonusCode->active];
        $activating = ! $bonusCode->active;

        if ($activating
            && $bonusCode->isSystemKind()
            && BonusCode::hasActiveSystemCampaign($bonusCode->resolvedKind(), (int) $bonusCode->id)) {
            return back()->withErrors([
                'kind' => 'Já existe um bônus deste tipo ativo nesta vigência.',
            ]);
        }

        $bonusCode->active = $activating;
        $bonusCode->save();

        $logger->record($request->user(), 'bonus_code.toggled', $bonusCode, $before, ['active' => $bonusCode->active]);

        return back()->with('status', 'Código atualizado.');
    }

    /**
     * @return array<string, string>|null
     */
    protected function validateKindFields(
        string $kind,
        float $bonusAmount,
        ?float $matchPercent,
        ?float $maxBonus,
        ?int $inactiveDays,
    ): ?array {
        return match ($kind) {
            BonusCode::KIND_FIXED, BonusCode::KIND_NEW_PLAYER, BonusCode::KIND_AFFILIATE_SIGNUP => $bonusAmount <= 0
                ? ['bonus_amount' => 'Informe um valor de bônus maior que zero.']
                : null,
            BonusCode::KIND_MATCH => ($matchPercent === null || $matchPercent <= 0)
                ? ['match_percent' => 'Informe o percentual de match (máx. 100).']
                : ($maxBonus === null || $maxBonus <= 0
                    ? ['max_bonus' => 'Informe o teto de bônus (obrigatório no match).']
                    : null),
            BonusCode::KIND_CASHBACK => ($matchPercent === null || $matchPercent <= 0)
                ? ['match_percent' => 'Informe o percentual (ex.: 10).']
                : null,
            BonusCode::KIND_FIRST_DEPOSIT => ($bonusAmount <= 0 && ($matchPercent === null || $matchPercent <= 0))
                ? ['bonus_amount' => 'Informe valor fixo ou percentual de match.']
                : (($matchPercent !== null && $matchPercent > 0 && ($maxBonus === null || $maxBonus <= 0))
                    ? ['max_bonus' => 'Informe o teto de bônus quando usar match %.']
                    : null),
            BonusCode::KIND_RELOAD => $inactiveDays === null || $inactiveDays < 1
                ? ['inactive_days' => 'Informe os dias de inatividade (mín. 1).']
                : (($bonusAmount <= 0 && ($matchPercent === null || $matchPercent <= 0))
                    ? ['bonus_amount' => 'Informe valor fixo ou percentual de match.']
                    : null),
            default => ['kind' => 'Tipo inválido.'],
        };
    }

    /**
     * @return array{0: float, 1: ?float, 2: ?float, 3: ?int}
     */
    protected function normalizeFields(
        string $kind,
        float $bonusAmount,
        ?float $matchPercent,
        ?float $maxBonus,
        ?int $inactiveDays,
    ): array {
        return match ($kind) {
            BonusCode::KIND_FIXED, BonusCode::KIND_NEW_PLAYER, BonusCode::KIND_AFFILIATE_SIGNUP => [$bonusAmount, null, null, null],
            BonusCode::KIND_MATCH, BonusCode::KIND_CASHBACK => [0.0, $matchPercent, $maxBonus, null],
            BonusCode::KIND_FIRST_DEPOSIT => [
                ($matchPercent && $matchPercent > 0) ? 0.0 : $bonusAmount,
                ($matchPercent && $matchPercent > 0) ? $matchPercent : null,
                ($matchPercent && $matchPercent > 0) ? $maxBonus : null,
                null,
            ],
            BonusCode::KIND_RELOAD => [
                ($matchPercent && $matchPercent > 0) ? 0.0 : $bonusAmount,
                ($matchPercent && $matchPercent > 0) ? $matchPercent : null,
                ($matchPercent && $matchPercent > 0) ? $maxBonus : null,
                $inactiveDays,
            ],
            default => [$bonusAmount, $matchPercent, $maxBonus, $inactiveDays],
        };
    }
}
