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
     * Get available payment gateways with enhanced information
     */
    public function getAvailableGateways(): array
    {
        $gateways = [];
        $supportedGateways = PaymentGatewayFactory::getSupportedGateways();

        foreach ($supportedGateways as $gatewayName) {
            try {
                $gateway = PaymentGatewayFactory::make($gatewayName);
                $gateways[] = [
                    'id' => $gatewayName,
                    'name' => ucfirst($gatewayName),
                    'description' => "Pay securely with {$gatewayName}",
                    'type' => 'redirect',
                    'supported_methods' => $gateway->getSupportedPaymentMethods(),
                    'supported_currencies' => $gateway->getSupportedCurrencies(),
                    'limits' => $gateway->getPaymentLimits()
                ];
            } catch (Exception $e) {
                Log::warning("Failed to load gateway info for {$gatewayName}", ['error' => $e->getMessage()]);
            }
        }

        return $gateways;
    }

    /**
     * Process payment with specific gateway - creates payment intent
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

                // Create payment intent with gateway
                $gatewayResult = $this->createPaymentIntent($gateway, $calculations['final_amount'], $paymentData, $order, $invoice);

                // Update invoice with payment details
                $invoice->update([
                    'transaction_id' => $gatewayResult['transaction_id'] ?? $gatewayResult['payment_intent_id'] ?? null,
                    'payment_details' => json_encode($gatewayResult)
                ]);

                Log::info('Payment intent created', [
                    'gateway' => $gateway,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'transaction_id' => $gatewayResult['transaction_id'] ?? null
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
     * Create payment intent with gateway
     */
    private function createPaymentIntent(string $gateway, float $amount, array $paymentData, Order $order, Invoice $invoice): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Prepare payment data
            $gatewayPaymentData = array_merge($paymentData, [
                'amount' => $amount,
                'currency' => 'USD',
                'description' => "Restaurant Order #{$order->id} Payment",
                'invoice_number' => $invoice->id,
                'return_url' => config('app.url') . "/api/payment/{$gateway}/success",
                'cancel_url' => config('app.url') . "/api/payment/{$gateway}/cancel",
                'metadata' => [
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'customer_id' => $order->user_id
                ]
            ]);

            return $paymentGateway->createPaymentIntent($gatewayPaymentData);

        } catch (Exception $e) {
            Log::error('Gateway payment intent creation failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle payment callback
     */
    public function handlePaymentCallback(string $gateway, $request): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            $result = $paymentGateway->callBack($request);

            Log::info('Payment callback handled', [
                'gateway' => $gateway,
                'success' => $result
            ]);

            return ['success' => $result];

        } catch (Exception $e) {
            Log::error('Payment callback failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Handle webhook notifications
     */
    public function handleWebhook(string $gateway, $request): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Validate webhook signature
            if (!$paymentGateway->validateWebhookSignature($request)) {
                throw new Exception('Invalid webhook signature');
            }

            // Handle the webhook
            $result = $paymentGateway->handleWebhook($request);

            Log::info('Webhook processed', [
                'gateway' => $gateway,
                'event_type' => $result['event_type'] ?? 'unknown'
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $gateway, string $transactionId): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            return $paymentGateway->getPaymentStatus($transactionId);

        } catch (Exception $e) {
            Log::error('Payment status check failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process refund
     */
    public function processRefund(string $gateway, string $transactionId, float $amount, ?string $reason = null): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            $result = $paymentGateway->processRefund($transactionId, $amount, $reason);

            Log::info('Refund processed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'success' => $result['success'] ?? false
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Refund processing failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel payment
     */
    public function cancelPayment(string $gateway, string $transactionId): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            $result = $paymentGateway->cancelPayment($transactionId);

            Log::info('Payment cancelled', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'success' => $result['success'] ?? false
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Payment cancellation failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Calculate transaction fees
     */
    public function calculateTransactionFees(string $gateway, float $amount, string $currency = 'USD'): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            return $paymentGateway->calculateTransactionFees($amount, $currency);

        } catch (Exception $e) {
            Log::error('Fee calculation failed', [
                'gateway' => $gateway,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test gateway connection
     */
    public function testGatewayConnection(string $gateway): array
    {
        try {
            $paymentGateway = PaymentGatewayFactory::make($gateway);
            return $paymentGateway->testConnection();

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => "Gateway connection test failed: {$e->getMessage()}",
                'gateway' => $gateway
            ];
        }
    }
}
