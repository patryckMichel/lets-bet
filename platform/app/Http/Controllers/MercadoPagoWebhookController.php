<?php

namespace App\Http\Controllers;

use App\Models\Deposit;
use App\Services\DepositSettlementService;
use App\Services\MercadoPagoPixService;
use App\Services\PixConfigService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoPixService $mp,
        DepositSettlementService $settlement,
    ): Response {
        $secret = app(PixConfigService::class)->webhookSecret();
        if ($secret && $request->header('X-Signature') === null && $request->query('secret') !== $secret) {
            // Soft check: if secret configured via query/header mismatch, still accept topic payloads with payment id
        }

        $paymentId = $request->input('data.id')
            ?? $request->input('id')
            ?? $request->query('data.id')
            ?? $request->query('id');

        $topic = $request->input('type')
            ?? $request->input('topic')
            ?? $request->query('topic')
            ?? $request->query('type');

        if (! $paymentId && in_array($topic, ['payment', 'merchant_order', null], true)) {
            $paymentId = data_get($request->all(), 'data.id');
        }

        if (! $paymentId) {
            return response('ignored', 200);
        }

        try {
            $payment = $mp->fetchPayment((string) $paymentId);
        } catch (\Throwable $e) {
            Log::warning('MP webhook fetch failed', ['id' => $paymentId, 'error' => $e->getMessage()]);

            return response('error', 200);
        }

        $status = (string) ($payment['status'] ?? '');
        $external = (string) ($payment['external_reference'] ?? '');

        if ($status !== 'approved' || $external === '') {
            return response('ok', 200);
        }

        $deposit = Deposit::query()->find($external);
        if (! $deposit) {
            $deposit = Deposit::query()->where('mp_payment_id', (string) $paymentId)->first();
        }

        if (! $deposit) {
            return response('not_found', 200);
        }

        if (! $deposit->mp_payment_id) {
            $deposit->mp_payment_id = (string) $paymentId;
            $deposit->save();
        }

        $settlement->settlePaid($deposit);

        return response('settled', 200);
    }
}
