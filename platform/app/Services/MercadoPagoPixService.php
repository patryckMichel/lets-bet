<?php

namespace App\Services;

use App\Models\Deposit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MercadoPagoPixService
{
    public function __construct(private PixConfigService $config) {}

    public function createPixPayment(Deposit $deposit, string $payerEmail): array
    {
        if ($this->config->provider() === 'static') {
            return $this->localFallback($deposit);
        }

        $token = $this->config->mercadoPagoAccessToken();

        if (! $token) {
            if (app()->environment('local')) {
                return $this->localFallback($deposit);
            }

            throw new RuntimeException('Mercado Pago não configurado. Cadastre o Access Token em Admin > Configurações.');
        }

        $idempotency = (string) Str::uuid();
        $amount = round((float) $deposit->amount, 2);

        $response = Http::withToken($token)
            ->acceptJson()
            ->withHeaders(['X-Idempotency-Key' => $idempotency])
            ->post('https://api.mercadopago.com/v1/payments', [
                'transaction_amount' => $amount,
                'description' => 'Depósito LESTBET 369 #'.$deposit->id,
                'payment_method_id' => 'pix',
                'payer' => [
                    'email' => $payerEmail,
                ],
                'external_reference' => (string) $deposit->id,
                'notification_url' => $this->config->webhookUrl(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao criar PIX no Mercado Pago: '.$response->body());
        }

        $data = $response->json();
        $txData = data_get($data, 'point_of_interaction.transaction_data', []);

        return [
            'mp_payment_id' => (string) ($data['id'] ?? ''),
            'pix_copy' => (string) ($txData['qr_code'] ?? ''),
            'qr_code_base64' => (string) ($txData['qr_code_base64'] ?? ''),
            'status' => (string) ($data['status'] ?? 'pending'),
        ];
    }

    public function fetchPayment(string $paymentId): array
    {
        $token = $this->config->mercadoPagoAccessToken();

        if (! $token) {
            throw new RuntimeException('Mercado Pago não configurado.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->get('https://api.mercadopago.com/v1/payments/'.$paymentId);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar pagamento MP: '.$response->body());
        }

        return $response->json();
    }

    private function localFallback(Deposit $deposit): array
    {
        $pix = app(PixBrCodeService::class)->generate((float) $deposit->amount, $deposit->txid);

        return [
            'mp_payment_id' => 'local-'.$deposit->id,
            'pix_copy' => $pix,
            'qr_code_base64' => '',
            'status' => 'pending',
        ];
    }
}
