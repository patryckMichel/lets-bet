<?php

namespace App\Services;

use App\Models\Deposit;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class PendingDepositCleanupService
{
    public function __construct(
        private AsaasPixService $asaas,
        private PixConfigService $pixConfig,
        private DepositSettlementService $settlement,
    ) {}

    public function ttlSeconds(): int
    {
        return max(30, (int) Setting::getValue('deposit_pix_ttl_seconds', 60));
    }

    public function expireDue(): int
    {
        $cutoff = now()->subSeconds($this->ttlSeconds());
        $count = 0;

        Deposit::query()
            ->where('status', Deposit::STATUS_PENDING)
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(50, function ($rows) use (&$count) {
                foreach ($rows as $deposit) {
                    if ($this->expireOne($deposit)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    public function expireOne(Deposit $deposit): bool
    {
        $deposit = $deposit->fresh();
        if (! $deposit || ! $deposit->isPending()) {
            return false;
        }

        if ($deposit->created_at->gt(now()->subSeconds($this->ttlSeconds()))) {
            return false;
        }

        // Se já foi pago no gateway, credita em vez de cancelar
        if ($deposit->mp_payment_id && $this->pixConfig->provider() === 'asaas') {
            try {
                $payment = $this->asaas->fetchPayment((string) $deposit->mp_payment_id);
                if ($this->asaas->isPaidStatus((string) ($payment['status'] ?? ''))) {
                    $net = isset($payment['netValue']) ? (float) $payment['netValue'] : null;
                    $this->settlement->settlePaid($deposit, $net);

                    return false;
                }

                $this->asaas->deletePayment((string) $deposit->mp_payment_id);
            } catch (\Throwable $e) {
                Log::warning('Falha ao cancelar PIX pendente no Asaas', [
                    'deposit_id' => $deposit->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $deposit->status = Deposit::STATUS_EXPIRED;
        $deposit->pix_copy = '';
        $deposit->qr_code_base64 = null;
        $deposit->save();

        return true;
    }
}
