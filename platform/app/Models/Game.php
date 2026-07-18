<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMING_SOON = 'coming_soon';

    public const STATUS_MAINTENANCE = 'maintenance';

    protected $fillable = [
        'slug',
        'name',
        'short_description',
        'category',
        'thumbnail',
        'launch_url',
        'status',
        'is_featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isPlayable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Jogar',
            self::STATUS_MAINTENANCE => 'Manutenção',
            default => 'Em breve',
        };
    }
}
