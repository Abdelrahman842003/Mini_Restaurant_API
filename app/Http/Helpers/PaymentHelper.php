<?php

namespace App\Http\Helpers;

class PaymentHelper
{
    /**
     * Payment options configuration
     */
    public static function getPaymentOptions(): array
    {
        return [
            1 => [
                'name' => 'Full Service Package',
                'description' => '14% taxes + 20% service charge',
                'tax_rate' => 0.14,
                'service_rate' => 0.20,
                'total_rate' => 0.34
            ],
            2 => [
                'name' => 'Service Only',
                'description' => '15% service charge only',
                'tax_rate' => 0.00,
                'service_rate' => 0.15,
                'total_rate' => 0.15
            ]
        ];
    }

    /**
     * Calculate payment breakdown
     */
    public static function calculatePaymentBreakdown(float $baseAmount, int $paymentOption): array
    {
        $options = self::getPaymentOptions();

        if (!isset($options[$paymentOption])) {
            throw new \InvalidArgumentException("Invalid payment option: {$paymentOption}");
        }

        $config = $options[$paymentOption];
        $taxAmount = round($baseAmount * $config['tax_rate'], 2);
        $serviceAmount = round($baseAmount * $config['service_rate'], 2);
        $finalAmount = round($baseAmount + $taxAmount + $serviceAmount, 2);

        return [
            'base_amount' => $baseAmount,
            'tax_amount' => $taxAmount,
            'service_charge_amount' => $serviceAmount,
            'final_amount' => $finalAmount,
            'payment_option' => $paymentOption,
            'option_details' => $config,
            'breakdown' => [
                'subtotal' => $baseAmount,
                'tax' => $taxAmount,
                'service' => $serviceAmount,
                'total' => $finalAmount
            ]
        ];
    }

    /**
     * Format amount for PayPal (2 decimal places)
     */
    public static function formatAmountForPayPal(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Validate payment amount
     */
    public static function validatePaymentAmount(float $amount): array
    {
        $errors = [];

        if ($amount <= 0) {
            $errors[] = 'Amount must be greater than zero';
        }

        if ($amount > 10000) {
            $errors[] = 'Amount exceeds maximum limit of $10,000';
        }

        if ($amount < 1) {
            $errors[] = 'Minimum payment amount is $1.00';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get supported currencies
     */
    public static function getSupportedCurrencies(): array
    {
        return [
            'USD' => ['name' => 'US Dollar', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'British Pound', 'symbol' => '£']
        ];
    }

    /**
     * Generate payment reference ID
     */
    public static function generatePaymentReference(int $orderId, int $invoiceId): string
    {
        return "ORD{$orderId}_INV{$invoiceId}_" . date('YmdHis');
    }
}
