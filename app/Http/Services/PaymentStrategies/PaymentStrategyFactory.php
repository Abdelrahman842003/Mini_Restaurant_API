<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentStrategyInterface;

class PaymentStrategyFactory
{
    /**
     * Create payment strategy based on option
     */
    public function create(int $paymentOption): PaymentStrategyInterface
    {
        return match ($paymentOption) {
            1 => new FullServiceStrategy(),
            2 => new ServiceOnlyStrategy(),
            default => throw new \InvalidArgumentException('Invalid payment option')
        };
    }
}
