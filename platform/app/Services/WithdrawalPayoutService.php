<?php

namespace App\Services;

use App\Models\FinanceEntry;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\PixKey;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WithdrawalPayoutService
{
    public function __construct(
        private AsaasPixService $asaas,
        private PixConfigService $pix,
        private FinanceLedgerService $ledger,
        private AdminLogger $logger,
        private AffiliateCommissionService $affiliates,
    ) {}

    public function payViaPix(Withdrawal $withdrawal, User $admin): Withdrawal
    {
        if (! $this->pix->isAsaasConfigured()) {
            throw new RuntimeException('Asaas não configurado. Cadastre a API Key em Configurações.');
        }

        $prepared = DB::transaction(function () use ($withdrawal) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if (! in_array($locked->status, [Withdrawal::STATUS_PENDING, Withdrawal::STATUS_APPROVED], true)) {
                throw new RuntimeException('Este saque não pode ser pago (status: '.$locked->status.').');
            }

            if ($locked->asaas_transfer_id) {
                throw new RuntimeException('Já existe uma transferência Asaas vinculada a este saque.');
            }

            $normalized = PixKey::normalize((string) $locked->pix_key);
            $locked->pix_key = $normalized['key'];
            $locked->pix_key_type = $normalized['type'];
            $locked->status = Withdrawal::STATUS_APPROVED;
            $locked->save();

            return [
                'id' => $locked->id,
                'amount' => (float) $locked->amount,
                'pix_key' => $normalized['key'],
                'pix_key_type' => $normalized['type'],
            ];
        });

        try {
            $transfer = $this->asaas->createPixTransfer(
                amount: $prepared['amount'],
                pixKey: $prepared['pix_key'],
                pixKeyType: $prepared['pix_key_type'],
                externalReference: 'withdrawal_'.$prepared['id'],
                description: 'Saque LESTBET #'.$prepared['id'],
            );
        } catch (\Throwable $e) {
            Withdrawal::query()->whereKey($prepared['id'])->update([
                'status' => Withdrawal::STATUS_PENDING,
                'admin_note' => 'Falha Asaas: '.$e->getMessage(),
            ]);

            throw $e instanceof RuntimeException
                ? $e
                : new RuntimeException('Falha ao criar transferência PIX no Asaas: '.$e->getMessage());
        }

        $transferId = (string) ($transfer['id'] ?? '');
        if ($transferId === '') {
            Withdrawal::query()->whereKey($prepared['id'])->update([
                'status' => Withdrawal::STATUS_PENDING,
                'admin_note' => 'Asaas não retornou o ID da transferência.',
            ]);

            throw new RuntimeException('Asaas não retornou o ID da transferência.');
        }

        return DB::transaction(function () use ($prepared, $transfer, $transferId, $admin) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()->whereKey($prepared['id'])->lockForUpdate()->firstOrFail();
            $before = $locked->only(['status', 'asaas_transfer_id', 'provider_status']);

            $locked->asaas_transfer_id = $transferId;
            $locked->provider_status = (string) ($transfer['status'] ?? 'PENDING');
            $locked->provider_payload = $transfer;
            $locked->status = Withdrawal::STATUS_APPROVED;
            $locked->processed_at = now();
            $locked->admin_note = trim(($locked->admin_note ? $locked->admin_note."\n" : '').'PIX enviado via Asaas por '.$admin->email);
            $locked->save();

            if ($this->asaas->isTransferDoneStatus($locked->provider_status)) {
                $this->markPaid($locked, $admin);
            }

            $this->logger->record($admin, 'withdrawal.pix_paid', $locked, $before, $locked->only([
                'status', 'asaas_transfer_id', 'provider_status', 'pix_key_type',
            ]));

            return $locked->fresh(['user']);
        });
    }

    public function reject(Withdrawal $withdrawal, User $admin, ?string $note = null): Withdrawal
    {
        return DB::transaction(function () use ($withdrawal, $admin, $note) {
            /** @var Withdrawal $locked */
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== Withdrawal::STATUS_PENDING) {
                throw new RuntimeException('Só é possível rejeitar saques pendentes.');
            }

            $before = $locked->only(['status', 'admin_note']);
            $isAffiliate = $locked->isAffiliateCommission();

            // Saque de jogador: devolve saldo. Comissão de afiliado: libera depósitos para novo cálculo.
            if (! $isAffiliate) {
                /** @var User $user */
                $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
                $user->balance = round((float) $user->balance + (float) $locked->amount, 2);
                $user->save();
            } else {
                $this->affiliates->releaseCommissionsForWithdrawal($locked);
            }

            $locked->status = Withdrawal::STATUS_REJECTED;
            $locked->processed_at = now();
            $defaultNote = $isAffiliate
                ? 'Comissão rejeitada — depósitos liberados para novo cálculo'
                : 'Rejeitado pelo admin';
            $locked->admin_note = trim(($note ?: $defaultNote).' · '.$admin->email);
            $locked->save();

            $this->logger->record($admin, 'withdrawal.rejected', $locked, $before, $locked->only([
                'status', 'admin_note',
            ]));

            return $locked->fresh(['user']);
        });
    }

    public function approveTransferValidation(string $transferId, ?float $value = null, ?string $externalReference = null): bool
    {
        $query = Withdrawal::query()->where('asaas_transfer_id', $transferId);

        if ($externalReference && str_starts_with($externalReference, 'withdrawal_')) {
            $id = (int) substr($externalReference, strlen('withdrawal_'));
            if ($id > 0) {
                $query = Withdrawal::query()->where(function ($q) use ($transferId, $id) {
                    $q->where('asaas_transfer_id', $transferId)
                        ->orWhere('id', $id);
                });
            }
        }

        $withdrawal = $query->first();
        if (! $withdrawal) {
            return false;
        }

        if ($value !== null && abs((float) $withdrawal->amount - $value) > 0.009) {
            Log::warning('Asaas transfer validation: valor diverge', [
                'withdrawal_id' => $withdrawal->id,
                'expected' => $withdrawal->amount,
                'got' => $value,
            ]);

            return false;
        }

        if ($withdrawal->asaas_transfer_id === null && $transferId !== '') {
            $withdrawal->asaas_transfer_id = $transferId;
            $withdrawal->save();
        }

        return in_array($withdrawal->status, [
            Withdrawal::STATUS_PENDING,
            Withdrawal::STATUS_APPROVED,
            Withdrawal::STATUS_PAID,
        ], true);
    }

    public function handleTransferWebhook(array $transfer, ?User $admin = null): void
    {
        $transferId = (string) ($transfer['id'] ?? '');
        if ($transferId === '') {
            return;
        }

        $withdrawal = Withdrawal::query()->where('asaas_transfer_id', $transferId)->first();
        if (! $withdrawal) {
            $ref = (string) ($transfer['externalReference'] ?? '');
            if (str_starts_with($ref, 'withdrawal_')) {
                $withdrawal = Withdrawal::query()->find((int) substr($ref, strlen('withdrawal_')));
            }
        }

        if (! $withdrawal) {
            return;
        }

        $status = strtoupper((string) ($transfer['status'] ?? ''));
        $withdrawal->provider_status = $status;
        $withdrawal->provider_payload = $transfer;
        $withdrawal->save();

        if ($this->asaas->isTransferDoneStatus($status) && $withdrawal->status !== Withdrawal::STATUS_PAID) {
            $this->markPaid($withdrawal, $admin);
        }

        if (in_array($status, ['FAILED', 'CANCELLED', 'REFUNDED'], true) && $withdrawal->status === Withdrawal::STATUS_APPROVED) {
            DB::transaction(function () use ($withdrawal, $status) {
                $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== Withdrawal::STATUS_APPROVED) {
                    return;
                }

                // Comissão de afiliado não debitou saldo — só reabre o saque.
                if (! $locked->isAffiliateCommission()) {
                    $user = User::query()->whereKey($locked->user_id)->lockForUpdate()->firstOrFail();
                    $user->balance = round((float) $user->balance + (float) $locked->amount, 2);
                    $user->save();
                }

                $locked->status = Withdrawal::STATUS_PENDING;
                $locked->asaas_transfer_id = null;
                $noteSuffix = $locked->isAffiliateCommission()
                    ? 'Transferência Asaas '.$status.' — saque de comissão reaberto.'
                    : 'Transferência Asaas '.$status.' — saldo estornado, saque reaberto.';
                $locked->admin_note = trim(($locked->admin_note ? $locked->admin_note."\n" : '').$noteSuffix);
                $locked->save();
            });
        }
    }

    protected function markPaid(Withdrawal $withdrawal, ?User $admin = null): void
    {
        DB::transaction(function () use ($withdrawal, $admin) {
            $locked = Withdrawal::query()->whereKey($withdrawal->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === Withdrawal::STATUS_PAID) {
                return;
            }

            $locked->status = Withdrawal::STATUS_PAID;
            $locked->processed_at = $locked->processed_at ?? now();
            $locked->save();

            if ($locked->isAffiliateCommission()) {
                $this->affiliates->markCommissionsPaidForWithdrawal($locked);
            }

            $this->ledger->record(
                FinanceEntry::TYPE_WITHDRAWAL,
                FinanceEntry::DIR_OUT,
                (float) $locked->amount,
                $locked,
                $admin,
                $locked->isAffiliateCommission()
                    ? 'Comissão afiliado PIX #'.$locked->id.' via Asaas'
                    : 'Saque PIX #'.$locked->id.' via Asaas'
            );
        });
    }
}
