<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentCalculatorInterface;

class TaxAndServiceCalculator implements PaymentCalculatorInterface
{
    public function calculate(float $subtotal): array
    {
        $taxAmount = $subtotal * 0.14; // 14% tax
        $serviceCharge = $subtotal * 0.20; // 20% service charge
        $totalAmount = $subtotal + $taxAmount + $serviceCharge;

        return [
            'tax_amount' => round($taxAmount, 2),
            'service_charge' => round($serviceCharge, 2),
            'total_amount' => round($totalAmount, 2)
        ];
    }
}
