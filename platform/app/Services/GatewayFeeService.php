<?php

namespace App\Services;

use App\Models\Setting;

class GatewayFeeService
{
    public function pixFee(float $grossAmount): float
    {
        return $this->calculate(
            $grossAmount,
            (float) Setting::getValue('fee_asaas_pix_percent', 0),
            (float) Setting::getValue('fee_asaas_pix_fixed', 0.99),
        );
    }

    public function boletoFee(float $grossAmount): float
    {
        return $this->calculate(
            $grossAmount,
            0,
            (float) Setting::getValue('fee_asaas_boleto_fixed', 0.99),
        );
    }

    public function creditCardFee(float $grossAmount): float
    {
        return $this->calculate(
            $grossAmount,
            (float) Setting::getValue('fee_asaas_card_percent', 1.99),
            (float) Setting::getValue('fee_asaas_card_fixed', 0.49),
        );
    }

    public function debitCardFee(float $grossAmount): float
    {
        return $this->calculate(
            $grossAmount,
            (float) Setting::getValue('fee_asaas_debit_percent', 1.89),
            (float) Setting::getValue('fee_asaas_debit_fixed', 0.35),
        );
    }

    /**
     * Prefer Asaas netValue when available; otherwise use configured PIX fee.
     *
     * @return array{fee: float, net: float}
     */
    public function resolveDepositFee(float $grossAmount, ?float $gatewayNetValue = null): array
    {
        $gross = round($grossAmount, 2);

        if ($gatewayNetValue !== null && $gatewayNetValue >= 0) {
            $net = round(min($gross, (float) $gatewayNetValue), 2);
            $fee = round(max(0, $gross - $net), 2);

            return ['fee' => $fee, 'net' => $net];
        }

        $fee = $this->pixFee($gross);
        $net = round(max(0, $gross - $fee), 2);

        return ['fee' => $fee, 'net' => $net];
    }

    private function calculate(float $gross, float $percent, float $fixed): float
    {
        $fee = ($gross * max(0, $percent) / 100) + max(0, $fixed);

        return round(min($gross, max(0, $fee)), 2);
    }
}
