<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Services\AsaasPixService;
use App\Services\DepositSettlementService;
use App\Services\PixConfigService;
use App\Services\WithdrawalPayoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class AsaasWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        AsaasPixService $asaas,
        DepositSettlementService $settlement,
        PixConfigService $config,
        WithdrawalPayoutService $payouts,
    ): Response {
        $expected = $config->asaasWebhookToken();
        if ($expected) {
            $incoming = $request->header('asaas-access-token')
                ?? $request->header('Asaas-Access-Token');

            if (! hash_equals($expected, (string) $incoming)) {
                Log::warning('Asaas webhook token inválido');

                return response('unauthorized', 401);
            }
        }

        $event = (string) $request->input('event', '');

        if (str_starts_with($event, 'TRANSFER_')) {
            $transfer = data_get($request->all(), 'transfer');
            if (is_array($transfer)) {
                $payouts->handleTransferWebhook($transfer);
            }

            return response('transfer_ok', 200);
        }

        $paymentId = (string) data_get($request->all(), 'payment.id', '');
        $external = (string) data_get($request->all(), 'payment.externalReference', '');
        $status = (string) data_get($request->all(), 'payment.status', '');

        if (! in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'], true) && ! $asaas->isPaidStatus($status)) {
            return response('ignored', 200);
        }

        if ($paymentId === '' && $external === '') {
            return response('ignored', 200);
        }

        $deposit = null;
        if ($external !== '') {
            $deposit = Deposit::query()->find($external);
        }
        if (! $deposit && $paymentId !== '') {
            $deposit = Deposit::query()->where('mp_payment_id', $paymentId)->first();
        }

        if (! $deposit) {
            return response('not_found', 200);
        }

        if (! $deposit->mp_payment_id && $paymentId !== '') {
            $deposit->mp_payment_id = $paymentId;
            $deposit->save();
        }

        try {
            $payment = $asaas->fetchPayment((string) ($deposit->mp_payment_id ?: $paymentId));
        } catch (\Throwable $e) {
            Log::warning('Asaas webhook fetch failed', ['id' => $paymentId, 'error' => $e->getMessage()]);

            return response('error', 200);
        }

        if (! $asaas->isPaidStatus((string) ($payment['status'] ?? ''))) {
            return response('pending', 200);
        }

        $netValue = isset($payment['netValue']) ? (float) $payment['netValue'] : null;
        $settlement->settlePaid($deposit, $netValue);

        return response('settled', 200);
    }
}
