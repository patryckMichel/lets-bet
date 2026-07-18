<?php

namespace App\Http\Controllers;

use App\Services\PixConfigService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint exigido pelo Asaas para liberar API Key (mecanismo de validação de saque).
 * Aprova apenas transferências que correspondem a um saque registrado na plataforma.
 */
class AsaasTransferValidationController extends Controller
{
    public function __invoke(
        Request $request,
        PixConfigService $config,
        WithdrawalPayoutService $payouts,
    ): JsonResponse {
        $expected = $config->asaasWebhookToken();
        if ($expected) {
            $incoming = $request->header('asaas-access-token')
                ?? $request->header('Asaas-Access-Token');

            if (! hash_equals($expected, (string) $incoming)) {
                Log::warning('Asaas transfer validation: token inválido');

                return response()->json([
                    'status' => 'REFUSED',
                    'refuseReason' => 'Token de autenticação inválido',
                ], 401);
            }
        }

        $payload = $request->all();
        $transfer = data_get($payload, 'transfer');
        if (! is_array($transfer)) {
            $transfer = $payload;
        }

        $id = (string) (data_get($transfer, 'id') ?? '');
        $value = data_get($transfer, 'value');
        $externalReference = data_get($transfer, 'externalReference');

        Log::info('Asaas transfer validation received', [
            'type' => (string) $request->input('type', ''),
            'id' => $id,
            'value' => $value,
            'externalReference' => $externalReference,
        ]);

        $ok = $payouts->approveTransferValidation(
            $id,
            is_numeric($value) ? (float) $value : null,
            is_string($externalReference) ? $externalReference : null,
        );

        if ($ok) {
            return response()->json(['status' => 'APPROVED']);
        }

        return response()->json([
            'status' => 'REFUSED',
            'refuseReason' => 'Transferência não reconhecida na plataforma LESTBET',
        ]);
    }
}
