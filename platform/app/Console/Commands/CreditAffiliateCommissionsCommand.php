<?php

namespace App\Console\Commands;

use App\Services\AffiliateCommissionService;
use Illuminate\Console\Command;

class CreditAffiliateCommissionsCommand extends Command
{
    protected $signature = 'affiliates:credit-due';

    protected $description = 'Legado: crédito automático desativado (use cálculo manual no admin)';

    public function handle(AffiliateCommissionService $service): int
    {
        $count = $service->creditDueCommissions();
        $this->info("Comissões creditadas: {$count}");

        return self::SUCCESS;
    }
}
