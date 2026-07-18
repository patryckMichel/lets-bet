<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $resolver = static function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value ?? $default;
        };

        try {
            return Cache::remember("setting:{$key}", 30, $resolver);
        } catch (\Throwable) {
            // Never break gameplay if file/redis cache is unavailable.
            return $resolver();
        }
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value]
        );

        try {
            Cache::forget("setting:{$key}");
        } catch (\Throwable) {
            // ignore
        }
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = static::getValue($key, $default ? '1' : '0');

        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) static::getValue($key, $default);
    }
}
