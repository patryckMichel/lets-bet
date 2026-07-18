<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AdminLogger;
use App\Services\PixConfigService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingAdminController extends Controller
{
    private array $houseKeys = [
        'house_edge',
        'max_multiplier',
        'deposit_min',
        'deposit_max',
        'withdrawal_min',
        'wagering_multiplier',
        'kyc_withdraw_threshold',
        'velocity_register_per_ip_day',
        'velocity_deposit_per_ip_day',
        'affiliate_signup_daily_cap',
        'affiliate_block_same_ip',
        'min_bet',
        'max_bet',
        'ghost_bets_enabled',
        'ghost_players_min',
        'ghost_players_max',
    ];

    private array $pixKeys = [
        'pix_provider',
        'pix_key',
        'pix_merchant_name',
        'pix_merchant_city',
        'mercadopago_access_token',
        'mercadopago_public_key',
        'mercadopago_webhook_secret',
        'mercadopago_webhook_url',
        'asaas_api_key',
        'asaas_webhook_token',
        'asaas_webhook_url',
    ];

    private array $feeKeys = [
        'fee_asaas_pix_fixed',
        'fee_asaas_pix_percent',
        'fee_asaas_boleto_fixed',
        'fee_asaas_card_percent',
        'fee_asaas_card_fixed',
        'fee_asaas_debit_percent',
        'fee_asaas_debit_fixed',
        'deposit_pix_ttl_seconds',
    ];

    private array $secretKeys = [
        'mercadopago_access_token',
        'mercadopago_webhook_secret',
        'asaas_api_key',
        'asaas_webhook_token',
    ];

    public function edit(PixConfigService $pix): View
    {
        $settings = [];
        foreach (array_merge($this->houseKeys, $this->pixKeys, $this->feeKeys) as $key) {
            $settings[$key] = Setting::getValue($key);
        }

        return view('admin.settings.edit', [
            'settings' => $settings,
            'pixConfigured' => $pix->isProviderConfigured(),
            'hasStoredToken' => $pix->hasStoredAccessToken(),
            'hasStoredAsaasApiKey' => $pix->hasStoredAsaasApiKey(),
            'hasStoredWebhookSecret' => $pix->hasStoredWebhookSecret(),
            'hasStoredAsaasWebhookToken' => $pix->hasStoredAsaasWebhookToken(),
            'defaultWebhookUrl' => url('/webhooks/mercadopago'),
            'defaultAsaasWebhookUrl' => url('/webhooks/asaas'),
        ]);
    }

    public function update(Request $request, AdminLogger $logger): RedirectResponse
    {
        $data = $request->validate([
            'house_edge' => ['required', 'numeric', 'min:0', 'max:0.5'],
            'max_multiplier' => ['required', 'numeric', 'min:2', 'max:1000'],
            'deposit_min' => ['required', 'numeric', 'min:1'],
            'deposit_max' => ['required', 'numeric', 'gte:deposit_min'],
            'withdrawal_min' => ['required', 'numeric', 'min:1'],
            'wagering_multiplier' => ['required', 'numeric', 'min:0', 'max:1000'],
            'kyc_withdraw_threshold' => ['required', 'numeric', 'min:0'],
            'velocity_register_per_ip_day' => ['required', 'integer', 'min:1', 'max:1000'],
            'velocity_deposit_per_ip_day' => ['required', 'integer', 'min:1', 'max:10000'],
            'affiliate_signup_daily_cap' => ['required', 'integer', 'min:0', 'max:100000'],
            'affiliate_block_same_ip' => ['required', 'in:0,1'],
            'min_bet' => ['required', 'numeric', 'min:0.01'],
            'max_bet' => ['required', 'numeric', 'gte:min_bet'],
            'ghost_bets_enabled' => ['nullable', 'boolean'],
            'ghost_players_min' => ['required', 'integer', 'min:0'],
            'ghost_players_max' => ['required', 'integer', 'gte:ghost_players_min'],
            'pix_provider' => ['required', 'in:asaas,mercadopago,static'],
            'pix_key' => ['nullable', 'string', 'max:120'],
            'pix_merchant_name' => ['nullable', 'string', 'max:25'],
            'pix_merchant_city' => ['nullable', 'string', 'max:15'],
            'mercadopago_access_token' => ['nullable', 'string', 'max:500'],
            'mercadopago_public_key' => ['nullable', 'string', 'max:500'],
            'mercadopago_webhook_secret' => ['nullable', 'string', 'max:500'],
            'mercadopago_webhook_url' => ['nullable', 'url', 'max:500'],
            'asaas_api_key' => ['nullable', 'string', 'max:1000'],
            'asaas_webhook_token' => ['nullable', 'string', 'max:500'],
            'asaas_webhook_url' => ['nullable', 'url', 'max:500'],
            'fee_asaas_pix_fixed' => ['required', 'numeric', 'min:0'],
            'fee_asaas_pix_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_asaas_boleto_fixed' => ['required', 'numeric', 'min:0'],
            'fee_asaas_card_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_asaas_card_fixed' => ['required', 'numeric', 'min:0'],
            'fee_asaas_debit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_asaas_debit_fixed' => ['required', 'numeric', 'min:0'],
            'deposit_pix_ttl_seconds' => ['required', 'integer', 'min:30', 'max:3600'],
        ]);

        if ($data['pix_provider'] === 'mercadopago') {
            $hasToken = Setting::getValue('mercadopago_access_token')
                || config('services.mercadopago.access_token')
                || filled($data['mercadopago_access_token'] ?? null);

            if (! $hasToken) {
                return back()
                    ->withInput()
                    ->withErrors(['mercadopago_access_token' => 'Informe o Access Token do Mercado Pago.']);
            }
        }

        if ($data['pix_provider'] === 'asaas') {
            $hasKey = Setting::getValue('asaas_api_key')
                || config('services.asaas.api_key')
                || filled($data['asaas_api_key'] ?? null);

            if (! $hasKey) {
                return back()
                    ->withInput()
                    ->withErrors(['asaas_api_key' => 'Informe a API Key do Asaas.']);
            }
        }

        if ($data['pix_provider'] === 'static') {
            $hasKey = Setting::getValue('pix_key')
                || config('services.pix.key')
                || filled($data['pix_key'] ?? null);

            if (! $hasKey) {
                return back()
                    ->withInput()
                    ->withErrors(['pix_key' => 'Informe a chave PIX para o modo estático.']);
            }
        }

        $before = [];
        $after = [];

        foreach ($this->houseKeys as $key) {
            $before[$key] = Setting::getValue($key);
            $value = $key === 'ghost_bets_enabled'
                ? ($request->boolean('ghost_bets_enabled') ? '1' : '0')
                : (string) $data[$key];
            Setting::setValue($key, $value);
            $after[$key] = $value;
            $this->forgetSettingCache($key);
        }

        foreach ($this->pixKeys as $key) {
            $before[$key] = $this->maskedPixValue($key, Setting::getValue($key));

            if (in_array($key, $this->secretKeys, true)) {
                if (blank($data[$key] ?? null)) {
                    continue;
                }
                $value = trim((string) $data[$key]);
            } else {
                $value = (string) ($data[$key] ?? '');
            }

            Setting::setValue($key, $value);
            $after[$key] = $this->maskedPixValue($key, $value);
            $this->forgetSettingCache($key);
        }

        foreach ($this->feeKeys as $key) {
            $before[$key] = Setting::getValue($key);
            $value = (string) $data[$key];
            Setting::setValue($key, $value);
            $after[$key] = $value;
            $this->forgetSettingCache($key);
        }

        $logger->record($request->user(), 'settings.updated', null, $before, $after);

        return back()->with('status', 'Configurações salvas.');
    }

    private function forgetSettingCache(string $key): void
    {
        Cache::forget("setting:{$key}");
        Cache::forget("setting:{$key}:bool");
    }

    private function maskedPixValue(string $key, mixed $value): string
    {
        if (! in_array($key, $this->secretKeys, true)) {
            return (string) $value;
        }

        $value = (string) $value;

        if ($value === '') {
            return '';
        }

        return '***'.substr($value, -4);
    }
}
