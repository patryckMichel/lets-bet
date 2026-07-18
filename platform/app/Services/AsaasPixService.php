<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\User;
use App\Support\Cpf;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasPixService
{
    public function __construct(private PixConfigService $config) {}

    public function createPixPayment(Deposit $deposit, User $user): array
    {
        $token = $this->config->asaasApiKey();
        if (! $token) {
            throw new RuntimeException('Asaas não configurado. Cadastre a API Key em Admin > Configurações.');
        }

        $cpf = Cpf::digits((string) ($user->cpf ?? ''));
        if (! Cpf::isValid($cpf)) {
            throw new RuntimeException('Informe um CPF válido para gerar o PIX.');
        }

        $customerId = $this->findOrCreateCustomer($user, $cpf);
        $amount = round((float) $deposit->amount, 2);
        $dueDate = now()->format('Y-m-d');

        $response = $this->client()->post('/payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $amount,
            'dueDate' => $dueDate,
            'description' => 'Depósito LESTBET 369 #'.$deposit->id,
            'externalReference' => (string) $deposit->id,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao criar cobrança PIX no Asaas: '.$this->errorMessage($response->json(), $response->body()));
        }

        $payment = $response->json();
        $paymentId = (string) ($payment['id'] ?? '');

        if ($paymentId === '') {
            throw new RuntimeException('Asaas não retornou o ID da cobrança.');
        }

        $qr = $this->fetchPixQrCode($paymentId);

        return [
            'mp_payment_id' => $paymentId,
            'pix_copy' => (string) ($qr['payload'] ?? ''),
            'qr_code_base64' => (string) ($qr['encodedImage'] ?? ''),
            'status' => (string) ($payment['status'] ?? 'PENDING'),
        ];
    }

    public function fetchPayment(string $paymentId): array
    {
        $response = $this->client()->get('/payments/'.$paymentId);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao consultar pagamento Asaas: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $response->json();
    }

    public function isPaidStatus(string $status): bool
    {
        return in_array(strtoupper($status), ['RECEIVED', 'CONFIRMED'], true);
    }

    public function deletePayment(string $paymentId): void
    {
        $response = $this->client()->delete('/payments/'.$paymentId);

        if ($response->successful()) {
            return;
        }

        // Já deletada / inexistente: ok para limpeza
        if (in_array($response->status(), [404, 400], true)) {
            return;
        }

        throw new RuntimeException('Falha ao excluir cobrança Asaas: '.$this->errorMessage($response->json(), $response->body()));
    }

    public function createPixTransfer(
        float $amount,
        string $pixKey,
        string $pixKeyType,
        string $externalReference,
        string $description,
    ): array {
        $token = $this->config->asaasApiKey();
        if (! $token) {
            throw new RuntimeException('Asaas não configurado. Cadastre a API Key em Admin > Configurações.');
        }

        $amount = round($amount, 2);
        if ($amount < 0.01) {
            throw new RuntimeException('Valor de transferência inválido.');
        }

        $response = $this->client()->post('/transfers', [
            'value' => $amount,
            'operationType' => 'PIX',
            'pixAddressKey' => $pixKey,
            'pixAddressKeyType' => $pixKeyType,
            'description' => $description,
            'externalReference' => $externalReference,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao criar transferência PIX no Asaas: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $response->json();
    }

    public function isTransferDoneStatus(string $status): bool
    {
        return in_array(strtoupper($status), ['DONE', 'COMPLETED', 'SUCCESS'], true);
    }

    private function fetchPixQrCode(string $paymentId): array
    {
        $response = $this->client()->get('/payments/'.$paymentId.'/pixQrCode');

        if (! $response->successful()) {
            throw new RuntimeException('Falha ao obter QR Code PIX no Asaas: '.$this->errorMessage($response->json(), $response->body()));
        }

        return $response->json();
    }

    private function findOrCreateCustomer(User $user, string $cpf): string
    {
        $externalRef = 'user_'.$user->id;

        $existingId = $this->findCustomerId([
            'externalReference' => $externalRef,
        ]) ?? $this->findCustomerId([
            'cpfCnpj' => $cpf,
        ]) ?? $this->findCustomerId([
            'email' => $user->email,
        ]);

        if ($existingId) {
            $this->ensureCustomerHasCpf($existingId, $cpf);

            return $existingId;
        }

        $create = $this->client()->post('/customers', [
            'name' => $user->name ?: 'Jogador LESTBET',
            'email' => $user->email,
            'cpfCnpj' => $cpf,
            'externalReference' => $externalRef,
            'notificationDisabled' => true,
        ]);

        if (! $create->successful()) {
            throw new RuntimeException('Falha ao criar cliente no Asaas: '.$this->errorMessage($create->json(), $create->body()));
        }

        $id = (string) ($create->json('id') ?? '');
        if ($id === '') {
            throw new RuntimeException('Asaas não retornou o ID do cliente.');
        }

        return $id;
    }

    private function findCustomerId(array $query): ?string
    {
        $search = $this->client()->get('/customers', $query);
        if (! $search->successful()) {
            return null;
        }

        $first = data_get($search->json(), 'data.0.id');

        return is_string($first) && $first !== '' ? $first : null;
    }

    private function ensureCustomerHasCpf(string $customerId, string $cpf): void
    {
        $get = $this->client()->get('/customers/'.$customerId);
        if (! $get->successful()) {
            return;
        }

        $current = Cpf::digits((string) ($get->json('cpfCnpj') ?? ''));
        if ($current !== '') {
            return;
        }

        $this->client()->put('/customers/'.$customerId, [
            'cpfCnpj' => $cpf,
        ]);
    }

    private function client()
    {
        $token = $this->config->asaasApiKey();

        return Http::baseUrl(rtrim($this->config->asaasBaseUrl(), '/'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $token,
                'User-Agent' => 'LESTBET369/1.0',
            ])
            ->timeout(30);
    }

    private function errorMessage(mixed $json, string $fallback): string
    {
        $errors = data_get($json, 'errors');
        if (is_array($errors) && $errors !== []) {
            $parts = [];
            foreach ($errors as $error) {
                $parts[] = (string) ($error['description'] ?? $error['code'] ?? json_encode($error));
            }

            return implode(' | ', $parts);
        }

        return $fallback;
    }
}
