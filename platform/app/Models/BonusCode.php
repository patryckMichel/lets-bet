<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BonusCode extends Model
{
    public const KIND_FIXED = 'fixed';

    public const KIND_MATCH = 'match';

    public const KIND_FIRST_DEPOSIT = 'first_deposit';

    public const KIND_CASHBACK = 'cashback';

    public const KIND_RELOAD = 'reload';

    public const KIND_NEW_PLAYER = 'new_player';

    public const KIND_AFFILIATE_SIGNUP = 'affiliate_signup';

    /** @deprecated Use KIND_* */
    public const TYPE_FIXED = self::KIND_FIXED;

    /** @deprecated Use KIND_* */
    public const TYPE_MATCH = self::KIND_MATCH;

    public const SYSTEM_KINDS = [
        self::KIND_FIRST_DEPOSIT,
        self::KIND_CASHBACK,
        self::KIND_RELOAD,
        self::KIND_NEW_PLAYER,
        self::KIND_AFFILIATE_SIGNUP,
    ];

    public const CODE_KINDS = [
        self::KIND_FIXED,
        self::KIND_MATCH,
    ];

    protected $fillable = [
        'code',
        'kind',
        'type',
        'affiliate_id',
        'bonus_amount',
        'match_percent',
        'max_bonus',
        'inactive_days',
        'max_uses',
        'uses_count',
        'expires_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'bonus_amount' => 'decimal:2',
            'match_percent' => 'decimal:2',
            'max_bonus' => 'decimal:2',
            'inactive_days' => 'integer',
            'expires_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BonusCodeRedemption::class);
    }

    public function resolvedKind(): string
    {
        $kind = (string) ($this->kind ?: $this->type ?: self::KIND_FIXED);

        return $kind !== '' ? $kind : self::KIND_FIXED;
    }

    public function isSystemKind(): bool
    {
        return in_array($this->resolvedKind(), self::SYSTEM_KINDS, true);
    }

    public function isCodeKind(): bool
    {
        return in_array($this->resolvedKind(), self::CODE_KINDS, true);
    }

    public function isUsable(): bool
    {
        if (! $this->active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->uses_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function hasBeenUsedBy(int $userId, string $periodKey = 'once'): bool
    {
        return $this->redemptions()
            ->where('user_id', $userId)
            ->where('period_key', $periodKey)
            ->exists();
    }

    public function isUsableBy(User $user, string $periodKey = 'once'): bool
    {
        return $this->isUsable() && ! $this->hasBeenUsedBy((int) $user->id, $periodKey);
    }

    public function calculateBonus(float $depositAmount): float
    {
        return $this->calculateDepositReward($depositAmount);
    }

    /**
     * Match% when set, otherwise fixed bonus_amount.
     */
    public function calculateDepositReward(float $depositAmount): float
    {
        $depositAmount = round($depositAmount, 2);
        $kind = $this->resolvedKind();

        if (in_array($kind, [self::KIND_FIXED, self::KIND_NEW_PLAYER, self::KIND_AFFILIATE_SIGNUP], true)) {
            return max(0, round((float) $this->bonus_amount, 2));
        }

        $percent = (float) ($this->match_percent ?? 0);
        if ($percent > 0) {
            $bonus = round($depositAmount * ($percent / 100), 2);
            if ($this->max_bonus !== null) {
                $bonus = min($bonus, (float) $this->max_bonus);
            }

            return max(0, round($bonus, 2));
        }

        return max(0, round((float) $this->bonus_amount, 2));
    }

    public function calculateCashback(float $netLoss): float
    {
        $netLoss = max(0, round($netLoss, 2));
        $percent = (float) ($this->match_percent ?? 0);
        $bonus = round($netLoss * ($percent / 100), 2);

        if ($this->max_bonus !== null) {
            $bonus = min($bonus, (float) $this->max_bonus);
        }

        return max(0, round($bonus, 2));
    }

    public function label(): string
    {
        return match ($this->resolvedKind()) {
            self::KIND_MATCH => $this->percentLabel().' do depósito',
            self::KIND_FIRST_DEPOSIT => '1º depósito: '.$this->rewardLabel(),
            self::KIND_RELOAD => 'Recarga ('.$this->inactive_days.'d): '.$this->rewardLabel(),
            self::KIND_CASHBACK => 'Cashback '.$this->percentLabel().(
                $this->max_bonus !== null
                    ? ' (máx $ '.number_format((float) $this->max_bonus, 2, '.', ',').')'
                    : ''
            ),
            self::KIND_NEW_PLAYER => '$ '.number_format((float) $this->bonus_amount, 2, '.', ',').' no cadastro',
            self::KIND_AFFILIATE_SIGNUP => '$ '.number_format((float) $this->bonus_amount, 2, '.', ',').' com código de afiliado',
            default => '$ '.number_format((float) $this->bonus_amount, 2, '.', ','),
        };
    }

    public function kindLabel(): string
    {
        return self::helpCatalog()[$this->resolvedKind()]['title'] ?? $this->resolvedKind();
    }

    /**
     * @return array{title: string, description: string, example: string, usage: string}
     */
    public static function helpFor(string $kind): array
    {
        return self::helpCatalog()[$kind] ?? [
            'title' => $kind,
            'description' => '',
            'example' => '',
            'usage' => '',
        ];
    }

    /**
     * @return array<string, array{title: string, description: string, example: string, usage: string}>
     */
    public static function helpCatalog(): array
    {
        return [
            self::KIND_MATCH => [
                'title' => 'Match %',
                'description' => 'Percentual do valor depositado creditado em bônus quando o jogador usa o código no PIX.',
                'example' => 'Deposita R$ 50 com match 100% → ganha R$ 50 de bônus.',
                'usage' => 'Crie o código e peça para o jogador digitar em Depositar. 1 uso por jogador.',
            ],
            self::KIND_FIXED => [
                'title' => 'Valor fixo',
                'description' => 'Valor fixo de bônus creditado quando o jogador resgata o código (sem precisar depositar).',
                'example' => 'Código dá +R$ 20 de bônus na tela Bônus.',
                'usage' => 'Crie o código e peça para o jogador digitar em Menu → Bônus. 1 uso por jogador.',
            ],
            self::KIND_FIRST_DEPOSIT => [
                'title' => '1º depósito',
                'description' => 'Bônus automático só no primeiro PIX pago do jogador (sem digitar código).',
                'example' => 'Match 100% ou valor fixo só na primeira recarga.',
                'usage' => 'Cadastre uma campanha ativa. Só pode haver 1 ativa nesta vigência. Não acumula com código digitado.',
            ],
            self::KIND_CASHBACK => [
                'title' => 'Cashback',
                'description' => 'Percentual das perdas líquidas do dia anterior creditado em bônus (job diário).',
                'example' => '10% do prejuízo de ontem, com teto opcional por dia.',
                'usage' => 'Cadastre 1 campanha ativa. O sistema aplica automaticamente todo dia. Não precisa de código.',
            ],
            self::KIND_RELOAD => [
                'title' => 'Recarga',
                'description' => 'Bônus na próxima recarga após X dias sem depósito pago.',
                'example' => '50% de match após 7 dias inativo.',
                'usage' => 'Defina os dias de inatividade e o prêmio (% ou fixo). 1 campanha ativa. Automático no depósito elegível.',
            ],
            self::KIND_NEW_PLAYER => [
                'title' => 'Novo jogador',
                'description' => 'Bônus fixo no cadastro, uma vez por usuário/CPF.',
                'example' => '+R$ 10 de bônus ao criar a conta.',
                'usage' => '1 campanha ativa. Exige CPF válido e único no registro (bloqueia cadastros falsos com o mesmo CPF).',
            ],
            self::KIND_AFFILIATE_SIGNUP => [
                'title' => 'Cadastro com afiliado',
                'description' => 'Bônus extra no primeiro depósito pago quando o jogador se cadastrou com código de afiliado. Soma com outros bônus de depósito.',
                'example' => '+R$ 50 de bônus no 1º PIX com código AFF…',
                'usage' => '1 campanha ativa. Crédito automático no 1º depósito. Teto diário global nas Configurações.',
            ],
        ];
    }

    public static function activeCampaign(string $kind): ?self
    {
        return self::query()
            ->where('kind', $kind)
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    public static function hasActiveSystemCampaign(string $kind, ?int $ignoreId = null): bool
    {
        if (! in_array($kind, self::SYSTEM_KINDS, true)) {
            return false;
        }

        $q = self::query()
            ->where('kind', $kind)
            ->where('active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }

        return $q->exists();
    }

    protected function percentLabel(): string
    {
        $pct = rtrim(rtrim(number_format((float) ($this->match_percent ?? 0), 2, '.', ''), '0'), '.');

        return $pct.'%';
    }

    protected function rewardLabel(): string
    {
        if ((float) ($this->match_percent ?? 0) > 0) {
            $label = $this->percentLabel();
            if ($this->max_bonus !== null) {
                $label .= ' (máx $ '.number_format((float) $this->max_bonus, 2, '.', ',').')';
            }

            return $label;
        }

        return '$ '.number_format((float) $this->bonus_amount, 2, '.', ',');
    }
}
