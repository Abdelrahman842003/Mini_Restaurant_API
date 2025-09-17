<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentStrategyInterface;

class FullServiceStrategy implements PaymentStrategyInterface
{
    /**
     * Calculate with 14% taxes + 20% service charge
     */
    public function calculate(float $baseAmount): array
    {
        $taxAmount = $baseAmount * 0.14;
        $serviceChargeAmount = $baseAmount * 0.20;
        $finalAmount = $baseAmount + $taxAmount + $serviceChargeAmount;

        return [
            'tax_amount' => round($taxAmount, 2),
            'service_charge_amount' => round($serviceChargeAmount, 2),
            'final_amount' => round($finalAmount, 2)
        ];
    }
}
