<?php

namespace App\Http\Factories;

use App\Http\Interfaces\PaymentGatewayInterface;
use App\Http\Services\PaymentGateways\StripeGateway;
use App\Http\Services\PaymentGateways\PayPalGateway;
use App\Http\Services\PaymentGateways\PaymobGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Create payment gateway instance based on gateway name
     *
     * @param string $gatewayName Gateway identifier (stripe, paypal, paymob)
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $gatewayName): PaymentGatewayInterface
    {
        return match (strtolower($gatewayName)) {
            'stripe' => new StripeGateway(),
            'paypal' => new PayPalGateway(),
            'paymob' => new PaymobGateway(),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayName}")
        };
    }

    /**
     * Get list of supported gateways
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return ['stripe', 'paypal', 'paymob'];
    }

    /**
     * Check if gateway is supported
     *
     * @param string $gatewayName
     * @return bool
     */
    public static function isSupported(string $gatewayName): bool
    {
        return in_array(strtolower($gatewayName), self::getSupportedGateways());
    }
}
