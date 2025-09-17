<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentStrategyInterface;

class ServiceOnlyStrategy implements PaymentStrategyInterface
{
    /**
     * Calculate with 15% service charge only
     */
    public function calculate(float $baseAmount): array
    {
        $taxAmount = 0;
        $serviceChargeAmount = $baseAmount * 0.15;
        $finalAmount = $baseAmount + $serviceChargeAmount;

        return [
            'tax_amount' => 0,
            'service_charge_amount' => round($serviceChargeAmount, 2),
            'final_amount' => round($finalAmount, 2)
        ];
    }
}
