<?php

namespace App\Http\Interfaces;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Create a payment transaction
     *
     * @param array $data Payment data including amount, customer info, etc.
     * @return array Response containing payment details and redirect URL if needed
     */
    public function createPayment(array $data): array;

    /**
     * Handle payment callback/webhook from gateway
     *
     * @param Request $request The callback request from payment gateway
     * @return array Response containing payment status and transaction details
     */
    public function handleCallback(Request $request): array;

    /**
     * Verify payment status directly from gateway API
     *
     * @param string $transactionId The transaction ID to verify
     * @return array Payment verification result
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Validate payment data before processing
     *
     * @param array $paymentData The payment data to validate
     * @return bool True if valid, throws exception if invalid
     */
    public function validatePaymentData(array $paymentData): bool;

    /**
     * Get gateway name
     *
     * @return string Gateway identifier
     */
    public function getGatewayName(): string;
}
