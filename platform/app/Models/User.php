<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'cpf',
        'password',
        'sexo',
        'data_nascimento',
        'estado',
        'cidade',
        'balance',
        'bonus_balance',
        'is_admin',
        'is_blocked',
        'affiliate_id',
        'registration_ip',
        'last_ip',
        'fraud_flag',
        'kyc_verified',
        'fraud_note',
        'wagering_required',
        'wagering_progress',
        'last_login_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'total_balance',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'data_nascimento' => 'date',
            'balance' => 'decimal:2',
            'bonus_balance' => 'decimal:2',
            'wagering_required' => 'decimal:2',
            'wagering_progress' => 'decimal:2',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
            'fraud_flag' => 'boolean',
            'kyc_verified' => 'boolean',
            'last_login_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function getTotalBalanceAttribute(): float
    {
        return round((float) $this->balance + (float) $this->bonus_balance, 2);
    }

    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value !== null ? strtolower(trim($value)) : null;
    }

    /** Conta de afiliado (este usuário é o afiliado). */
    public function affiliate(): HasOne
    {
        return $this->hasOne(Affiliate::class);
    }

    /** Afiliado que indicou este jogador (carteira). */
    public function referredByAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    public function isAffiliate(): bool
    {
        return $this->affiliate()->exists();
    }
}
