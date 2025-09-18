<?php

namespace App\Http\Factories;

use App\Http\Interfaces\PaymentGatewayInterface;
use InvalidArgumentException;

class PaymentGatewayFactory
{
    /**
     * Supported payment gateways
     */
    private static array $supportedGateways = [
        'paypal' => \App\Http\Services\PaymentGateways\PaypalPaymentService::class,
    ];

    /**
     * Create payment gateway instance
     *
     * @param string $gateway Gateway name
     * @return PaymentGatewayInterface
     * @throws InvalidArgumentException
     */
    public static function make(string $gateway): PaymentGatewayInterface
    {
        if (!self::isSupported($gateway)) {
            throw new InvalidArgumentException(
                "Unsupported payment gateway: {$gateway}. Supported gateways: " .
                implode(', ', self::getSupportedGateways())
            );
        }

        $gatewayClass = self::$supportedGateways[$gateway];

        return app($gatewayClass);
    }

    /**
     * Check if gateway is supported
     *
     * @param string $gateway
     * @return bool
     */
    public static function isSupported(string $gateway): bool
    {
        return array_key_exists($gateway, self::$supportedGateways);
    }

    /**
     * Get list of supported gateways
     *
     * @return array
     */
    public static function getSupportedGateways(): array
    {
        return array_keys(self::$supportedGateways);
    }

    /**
     * Register new payment gateway
     *
     * @param string $name Gateway name
     * @param string $class Gateway class (must implement PaymentGatewayInterface)
     * @return void
     */
    public static function register(string $name, string $class): void
    {
        if (!is_subclass_of($class, PaymentGatewayInterface::class)) {
            throw new InvalidArgumentException(
                "Gateway class {$class} must implement PaymentGatewayInterface"
            );
        }

        self::$supportedGateways[$name] = $class;
    }

    /**
     * Get gateway configuration
     *
     * @param string $gateway
     * @return array
     */
    public static function getGatewayConfig(string $gateway): array
    {
        return match ($gateway) {
            'paypal' => [
                'name' => 'PayPal',
                'description' => 'Pay securely with PayPal worldwide',
                'type' => 'redirect',
                'icon' => 'paypal-icon.png',
                'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'],
                'min_amount' => 1.00,
                'max_amount' => 10000.00,
                'countries' => ['US', 'GB', 'CA', 'AU', 'DE', 'FR', 'IT', 'ES']
            ],
            default => throw new InvalidArgumentException("Unknown gateway: {$gateway}")
        };
    }
}
