<?php

namespace App\Support;

class PixKey
{
    /**
     * @return array{key: string, type: string}
     */
    public static function normalize(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            throw new \InvalidArgumentException('Chave PIX vazia.');
        }

        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return ['key' => strtolower($raw), 'type' => 'EMAIL'];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (strlen($digits) === 11 && Cpf::isValid($digits)) {
            return ['key' => $digits, 'type' => 'CPF'];
        }

        if (strlen($digits) === 14) {
            return ['key' => $digits, 'type' => 'CNPJ'];
        }

        if (strlen($digits) === 11 && preg_match('/^[1-9]{2}9\d{8}$/', $digits)) {
            return ['key' => $digits, 'type' => 'PHONE'];
        }

        if (strlen($digits) === 10 && preg_match('/^[1-9]{2}\d{8}$/', $digits)) {
            return ['key' => $digits, 'type' => 'PHONE'];
        }

        // Chave aleatória (EVP) — UUID-like ou string livre
        return ['key' => $raw, 'type' => 'EVP'];
    }
}
