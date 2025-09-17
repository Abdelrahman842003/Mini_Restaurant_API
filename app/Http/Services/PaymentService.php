<?php

namespace App\Http\Services;

use App\Http\Interfaces\InvoiceRepositoryInterface;
use App\Http\Interfaces\OrderRepositoryInterface;
use App\Http\Services\PaymentStrategies\PaymentStrategyFactory;
use App\Http\Factories\PaymentGatewayFactory;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private InvoiceRepositoryInterface $invoiceRepository,
        private PaymentStrategyFactory $paymentStrategyFactory
    ) {}

    /**
     * Get available payment methods - Option 1 and Option 2
     */
    public function getPaymentMethods(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Full Service Package',
                'description' => '14% taxes + 20% service charge',
                'tax_rate' => 0.14,
                'service_charge_rate' => 0.20
            ],
            [
                'id' => 2,
                'name' => 'Service Only',
                'description' => '15% service charge only',
                'tax_rate' => 0,
                'service_charge_rate' => 0.15
            ]
        ];
    }

    /**
     * Get available payment gateways - PayPal only
     */
    public function getAvailableGateways(): array
    {
        return [
            [
                'id' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay securely with PayPal worldwide',
                'type' => 'redirect',
                'icon' => 'paypal-icon.png',
                'currencies' => ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY']
            ]
        ];
    }

    /**
     * Process payment with specific gateway - ONLY creates payment intent, does NOT update order status
     */
    public function processPaymentWithGateway(Order $order, int $paymentOption, string $gateway, array $paymentData = []): array
    {
        return DB::transaction(function () use ($order, $paymentOption, $gateway, $paymentData) {
            try {
                // Verify order is not already paid
                if ($order->status === 'paid') {
                    throw new Exception('Order is already paid.');
                }

                // Validate gateway using Factory
                if (!PaymentGatewayFactory::isSupported($gateway)) {
                    throw new Exception("Unsupported payment gateway: {$gateway}. Supported gateways: " . implode(', ', PaymentGatewayFactory::getSupportedGateways()));
                }

                // Get payment strategy based on option
                $paymentStrategy = $this->paymentStrategyFactory->create($paymentOption);

                // Calculate amounts using strategy
                $calculations = $paymentStrategy->calculate($order->total_amount);

                // Create invoice with pending status
                $invoice = $this->invoiceRepository->create([
                    'order_id' => $order->id,
                    'payment_option' => $paymentOption,
                    'tax_amount' => $calculations['tax_amount'],
                    'service_charge_amount' => $calculations['service_charge_amount'],
                    'final_amount' => $calculations['final_amount'],
                    'payment_gateway' => $gateway,
                    'payment_status' => 'pending'
                ]);

                // Create payment intent with gateway (NO status updates here)
                $gatewayResult = $this->createPaymentIntent($gateway, $calculations['final_amount'], $paymentData, $order, $invoice);

                // Update invoice with payment details only
                $invoice->update([
                    'transaction_id' => $gatewayResult['transaction_id'] ?? null,
                    'payment_details' => json_encode($gatewayResult)
                ]);

                Log::info('Payment intent created', [
                    'gateway' => $gateway,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $gatewayResult['transaction_id'] ?? null,
                    'redirect_required' => $gatewayResult['redirect_required'] ?? false
                ]);

                return [
                    'invoice' => $invoice,
                    'payment_result' => $gatewayResult
                ];

            } catch (Exception $e) {
                Log::error('Payment intent creation failed', [
                    'gateway' => $gateway,
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Create payment intent with gateway (renamed from processWithGateway for clarity)
     */
    private function createPaymentIntent(string $gateway, float $amount, array $paymentData, Order $order, Invoice $invoice): array
    {
        try {
            // Use Factory Pattern to create the appropriate gateway
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Prepare payment data with order context
            $gatewayPaymentData = array_merge($paymentData, [
                'amount' => $amount,
                'description' => "Restaurant Order #{$order->id} Payment",
                'invoice_number' => $invoice->id,
                'metadata' => [
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $order->user_id
                ],
                'customer' => [
                    'first_name' => $order->user->name ?? 'Customer',
                    'email' => $order->user->email ?? 'customer@example.com',
                    'phone' => $order->user->phone ?? '+201000000000'
                ]
            ]);

            // Add gateway-specific URLs
            if ($gateway === 'paypal') {
                $gatewayPaymentData['return_url'] = config('app.url') . '/api/payment/paypal/success';
                $gatewayPaymentData['cancel_url'] = config('app.url') . '/api/payment/paypal/cancel';
            }

            // Create payment using the gateway
            $result = $paymentGateway->createPayment($gatewayPaymentData);

            Log::info('Gateway payment intent created', [
                'gateway' => $gateway,
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Gateway payment intent creation failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => $gateway
            ];
        }
    }

    /**
     * Handle payment callback and capture payment - THIS is where status updates happen
     */
    public function handlePaymentCallback(string $gateway, $request): array
    {
        try {
            // Use Factory Pattern to get the appropriate gateway
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Handle callback and capture payment using the gateway
            $result = $paymentGateway->handleCallback($request);

            Log::info('Payment callback handled', [
                'gateway' => $gateway,
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null,
                'status' => $result['status'] ?? 'unknown'
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Payment callback handling failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => $gateway
            ];
        }
    }

    /**
     * Update payment status after successful callback - called by PaymentCallbackController
     */
    public function updatePaymentStatus(string $transactionId, string $status, array $paymentDetails = []): bool
    {
        return DB::transaction(function () use ($transactionId, $status, $paymentDetails) {
            try {
                // Find invoice by transaction ID
                $invoice = Invoice::where('transaction_id', $transactionId)->first();

                if (!$invoice) {
                    Log::warning('Invoice not found for transaction', ['transaction_id' => $transactionId]);
                    return false;
                }

                $paymentStatus = match ($status) {
                    'completed', 'COMPLETED' => 'completed',
                    'failed', 'FAILED' => 'failed',
                    'cancelled', 'CANCELLED' => 'cancelled',
                    default => 'pending'
                };

                $orderStatus = match ($status) {
                    'completed', 'COMPLETED' => 'paid',
                    'failed', 'FAILED' => 'payment_failed',
                    'cancelled', 'CANCELLED' => 'cancelled',
                    default => 'pending'
                };

                // Update invoice
                $invoice->update([
                    'payment_status' => $paymentStatus,
                    'payment_details' => json_encode(array_merge(
                        json_decode($invoice->payment_details, true) ?? [],
                        [
                            'callback_result' => $paymentDetails,
                            'captured_at' => now()->toISOString()
                        ]
                    ))
                ]);

                // Update order
                $invoice->order->update(['status' => $orderStatus]);

                Log::info('Payment status updated successfully', [
                    'transaction_id' => $transactionId,
                    'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id,
                    'payment_status' => $paymentStatus,
                    'order_status' => $orderStatus
                ]);

                return true;

            } catch (Exception $e) {
                Log::error('Failed to update payment status', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage()
                ]);
                return false;
            }
        });
    }

    /**
     * Verify payment status using Factory Pattern
     */
    public function verifyPaymentStatus(string $gateway, string $transactionId): array
    {
        try {
            // Use Factory Pattern to get the appropriate gateway
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Verify payment using the gateway
            $result = $paymentGateway->verifyPayment($transactionId);

            Log::info('Payment verification completed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'verified' => $result['verified'] ?? false
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'verified' => false
            ];
        }
    }
}
