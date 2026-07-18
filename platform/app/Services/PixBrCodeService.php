<?php

namespace App\Services;

/**
 * Gera payload PIX "copia e cola" (BR Code EMV) estático com valor.
 */
class PixBrCodeService
{
    public function __construct(private PixConfigService $config) {}

    public function generate(float $amount, string $txid): string
    {
        $key = $this->config->pixKey();
        $name = $this->sanitizeMerchant($this->config->merchantName(), 25);
        $city = $this->sanitizeMerchant($this->config->merchantCity(), 15);
        $txid = substr(preg_replace('/[^A-Za-z0-9]/', '', $txid) ?: 'LESTBET', 0, 25);
        $amount = number_format(round($amount, 2), 2, '.', '');

        $merchantAccount = $this->tlv('00', 'BR.GOV.BCB.PIX')
            .$this->tlv('01', $key);

        $additional = $this->tlv('05', $txid);

        $payload = $this->tlv('00', '01')
            .$this->tlv('26', $merchantAccount)
            .$this->tlv('52', '0000')
            .$this->tlv('53', '986')
            .$this->tlv('54', $amount)
            .$this->tlv('58', 'BR')
            .$this->tlv('59', $name)
            .$this->tlv('60', $city)
            .$this->tlv('62', $additional)
            .'6304';

        return $payload.$this->crc16($payload);
    }

    private function tlv(string $id, string $value): string
    {
        $len = str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);

        return $id.$len.$value;
    }

    private function sanitizeMerchant(string $value, int $max): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtoupper(preg_replace('/[^A-Z0-9 ]/', '', $value) ?: 'LESTBET');

        return substr(trim($value) ?: 'LESTBET', 0, $max);
    }

    private function crc16(string $payload): string
    {
        $polynomial = 0x1021;
        $result = 0xFFFF;

        for ($offset = 0, $len = strlen($payload); $offset < $len; $offset++) {
            $result ^= (ord($payload[$offset]) << 8);
            for ($bit = 0; $bit < 8; $bit++) {
                if (($result & 0x8000) !== 0) {
                    $result = (($result << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $result = ($result << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($result), 4, '0', STR_PAD_LEFT));
    }
}
