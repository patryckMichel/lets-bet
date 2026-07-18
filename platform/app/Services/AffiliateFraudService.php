<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Setting;
use App\Models\User;
use App\Support\Cpf;
use RuntimeException;

class AffiliateFraudService
{
    public function assertCanLink(Affiliate $affiliate, string $cpfDigits, ?string $ip): void
    {
        $affiliate->loadMissing('user');
        $owner = $affiliate->user;
        if (! $owner) {
            return;
        }

        $ownerCpf = Cpf::digits((string) ($owner->cpf ?? ''));
        if ($ownerCpf !== '' && $ownerCpf === $cpfDigits) {
            throw new RuntimeException('Não é permitido usar o próprio código de afiliado.');
        }

        if (! filter_var(Setting::getValue('affiliate_block_same_ip', '1'), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ($ip === null || $ip === '') {
            return;
        }

        $ownerIps = array_filter([
            (string) ($owner->registration_ip ?? ''),
            (string) ($owner->last_ip ?? ''),
            (string) ($owner->ip_address ?? ''),
        ]);

        if (in_array($ip, $ownerIps, true)) {
            throw new RuntimeException('Código de afiliado inválido para este dispositivo/rede.');
        }
    }

    public function markSuspicious(User $user, string $reason): void
    {
        $user->fraud_flag = true;
        $note = trim((string) ($user->fraud_note ?? ''));
        $line = now()->format('d/m/Y H:i').' · '.$reason;
        $user->fraud_note = $note === '' ? $line : $note."\n".$line;
        $user->save();
    }

    public function assertWithdrawalPixNotAffiliate(User $user, string $pixKey): void
    {
        if (! $user->affiliate_id) {
            return;
        }

        $affiliate = Affiliate::query()->whereKey($user->affiliate_id)->first();
        if (! $affiliate || ! $affiliate->hasPixKey()) {
            return;
        }

        $a = $this->normalizePix($pixKey);
        $b = $this->normalizePix((string) $affiliate->pix_key);
        if ($a !== '' && $a === $b) {
            $this->markSuspicious($user, 'Saque com mesma chave PIX do afiliado #'.$affiliate->id);
            throw new RuntimeException('Saque bloqueado para análise de segurança. Contate o suporte.');
        }
    }

    protected function normalizePix(string $key): string
    {
        $key = strtolower(trim($key));
        $digits = preg_replace('/\D+/', '', $key) ?? '';
        if (strlen($digits) >= 11) {
            return $digits;
        }

        return $key;
    }
}
