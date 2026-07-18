<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class VelocityService
{
    public function assertRegisterAllowed(?string $ip): void
    {
        $this->assert('register', $ip, (int) Setting::getValue('velocity_register_per_ip_day', 3), 'Muitas contas deste IP hoje. Tente novamente amanhã.');
    }

    public function assertDepositAllowed(?string $ip): void
    {
        $this->assert('deposit', $ip, (int) Setting::getValue('velocity_deposit_per_ip_day', 10), 'Limite de depósitos deste IP atingido. Tente novamente amanhã.');
    }

    public function hitRegister(?string $ip): void
    {
        $this->hit('register', $ip);
    }

    public function hitDeposit(?string $ip): void
    {
        $this->hit('deposit', $ip);
    }

    protected function assert(string $kind, ?string $ip, int $limit, string $message): void
    {
        if ($ip === null || $ip === '' || $limit < 1) {
            return;
        }

        $count = (int) Cache::get($this->key($kind, $ip), 0);
        if ($count >= $limit) {
            throw new RuntimeException($message);
        }
    }

    protected function hit(string $kind, ?string $ip): void
    {
        if ($ip === null || $ip === '') {
            return;
        }

        $key = $this->key($kind, $ip);
        if (! Cache::has($key)) {
            Cache::put($key, 1, now()->endOfDay());

            return;
        }

        Cache::increment($key);
    }

    protected function key(string $kind, string $ip): string
    {
        return 'velocity:'.$kind.':'.$ip.':'.now()->toDateString();
    }
}
