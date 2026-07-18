<?php

namespace App\Services;

use App\Models\Setting;

class PixConfigService
{
    public function provider(): string
    {
        $value = Setting::getValue('pix_provider');

        if ($value) {
            return (string) $value;
        }

        if ($this->asaasApiKey()) {
            return 'asaas';
        }

        return $this->mercadoPagoAccessToken() ? 'mercadopago' : 'static';
    }

    public function isProviderConfigured(): bool
    {
        return match ($this->provider()) {
            'asaas' => $this->asaasApiKey() !== null,
            'mercadopago' => $this->mercadoPagoAccessToken() !== null,
            default => false,
        };
    }

    public function isMercadoPagoConfigured(): bool
    {
        return $this->provider() === 'mercadopago' && $this->mercadoPagoAccessToken() !== null;
    }

    public function isAsaasConfigured(): bool
    {
        return $this->provider() === 'asaas' && $this->asaasApiKey() !== null;
    }

    public function accessToken(): ?string
    {
        return $this->mercadoPagoAccessToken();
    }

    public function mercadoPagoAccessToken(): ?string
    {
        $value = Setting::getValue('mercadopago_access_token');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.mercadopago.access_token');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function publicKey(): ?string
    {
        $value = Setting::getValue('mercadopago_public_key');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.mercadopago.public_key');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function webhookSecret(): ?string
    {
        $value = Setting::getValue('mercadopago_webhook_secret');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.mercadopago.webhook_secret');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function webhookUrl(): string
    {
        $value = Setting::getValue('mercadopago_webhook_url');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.mercadopago.webhook_url');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        return url('/webhooks/mercadopago');
    }

    public function asaasApiKey(): ?string
    {
        $value = Setting::getValue('asaas_api_key');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.asaas.api_key');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function asaasWebhookToken(): ?string
    {
        $value = Setting::getValue('asaas_webhook_token');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.asaas.webhook_token');

        return is_string($env) && $env !== '' ? $env : null;
    }

    public function asaasWebhookUrl(): string
    {
        $value = Setting::getValue('asaas_webhook_url');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $env = config('services.asaas.webhook_url');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        return url('/webhooks/asaas');
    }

    public function asaasBaseUrl(): string
    {
        $env = config('services.asaas.base_url');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        $key = (string) ($this->asaasApiKey() ?? '');

        if (str_contains($key, '_hmlg_') || str_contains($key, 'sandbox')) {
            return 'https://api-sandbox.asaas.com/v3';
        }

        return 'https://api.asaas.com/v3';
    }

    public function pixKey(): string
    {
        return (string) (Setting::getValue('pix_key') ?: config('services.pix.key', ''));
    }

    public function merchantName(): string
    {
        return (string) (Setting::getValue('pix_merchant_name') ?: config('services.pix.merchant_name', 'LESTBET 369'));
    }

    public function merchantCity(): string
    {
        return (string) (Setting::getValue('pix_merchant_city') ?: config('services.pix.merchant_city', 'SAO PAULO'));
    }

    public function hasStoredAccessToken(): bool
    {
        $value = Setting::getValue('mercadopago_access_token');

        return is_string($value) && $value !== '';
    }

    public function hasStoredAsaasApiKey(): bool
    {
        $value = Setting::getValue('asaas_api_key');

        return is_string($value) && $value !== '';
    }

    public function hasStoredWebhookSecret(): bool
    {
        $value = Setting::getValue('mercadopago_webhook_secret');

        return is_string($value) && $value !== '';
    }

    public function hasStoredAsaasWebhookToken(): bool
    {
        $value = Setting::getValue('asaas_webhook_token');

        return is_string($value) && $value !== '';
    }
}
