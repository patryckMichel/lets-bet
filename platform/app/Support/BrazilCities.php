<?php

namespace App\Support;

class BrazilCities
{
    /** @var array<string, list<string>>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, list<string>>
     */
    public static function allByUf(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = public_path('data/br-cities-by-uf.json');
        if (! is_file($path)) {
            return self::$cache = [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded)) {
            return self::$cache = [];
        }

        /** @var array<string, list<string>> $decoded */
        return self::$cache = $decoded;
    }

    /**
     * @return list<string>
     */
    public static function forUf(string $uf): array
    {
        $uf = strtoupper(trim($uf));

        return self::allByUf()[$uf] ?? [];
    }

    public static function belongsToUf(string $city, string $uf): bool
    {
        $city = trim($city);
        $cities = self::forUf($uf);

        foreach ($cities as $name) {
            if (mb_strtolower($name) === mb_strtolower($city)) {
                return true;
            }
        }

        return false;
    }

    public static function normalizeCityName(string $city, string $uf): ?string
    {
        $city = trim($city);
        foreach (self::forUf($uf) as $name) {
            if (mb_strtolower($name) === mb_strtolower($city)) {
                return $name;
            }
        }

        return null;
    }
}
