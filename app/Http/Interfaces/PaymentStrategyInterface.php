<?php

namespace App\Http\Interfaces;

interface PaymentStrategyInterface
{
    public function calculate(float $baseAmount): array;
}
