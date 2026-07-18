<?php

namespace App\Console\Commands;

use App\Services\PendingDepositCleanupService;
use Illuminate\Console\Command;

class ExpirePendingDepositsCommand extends Command
{
    protected $signature = 'deposits:expire-pending';

    protected $description = 'Expira PIX pendentes após o TTL e cancela a cobrança no Asaas';

    public function handle(PendingDepositCleanupService $cleanup): int
    {
        $count = $cleanup->expireDue();
        $this->info("Expirados: {$count}");

        return self::SUCCESS;
    }
}
