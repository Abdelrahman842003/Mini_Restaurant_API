<?php

namespace App\Http\Factories;

use App\Http\Interfaces\PaymentGatewayInterface;
use App\Http\Services\PaymentGateways\PayPalGateway;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Create payment gateway instance based on gateway name
     *
     * @param string $gatewayName Gateway identifier (paypal only)
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $gatewayName): PaymentGatewayInterface
    {
        return match (strtolower($gatewayName)) {
            'paypal' => new PayPalGateway(),
            default => throw new InvalidArgumentException("Unsupported payment gateway: {$gatewayName}. Only PayPal is supported.")
        };
    }

    /**
     * Get list of supported gateways
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return ['paypal'];
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
