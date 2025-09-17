<?php

namespace App\Http\Interfaces;

interface PaymentCalculatorInterface
{
    public function calculate(float $subtotal): array;
}
