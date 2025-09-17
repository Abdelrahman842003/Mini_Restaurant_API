<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentCalculatorInterface;

class ServiceOnlyCalculator implements PaymentCalculatorInterface
{
    public function calculate(float $subtotal): array
    {
        $serviceCharge = $subtotal * 0.15; // 15% service charge only
        $totalAmount = $subtotal + $serviceCharge;

        return [
            'tax_amount' => 0,
            'service_charge' => round($serviceCharge, 2),
            'total_amount' => round($totalAmount, 2)
        ];
    }
}
