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
     * Process payment for an order (legacy method for backward compatibility)
     */
    public function processPayment(Order $order, int $paymentOption): Invoice
    {
        return DB::transaction(function () use ($order, $paymentOption) {
            // Verify order is not already paid
            if ($order->status === 'paid') {
                throw new Exception('Order is already paid.');
            }

            // Get payment strategy based on option
            $paymentStrategy = $this->paymentStrategyFactory->create($paymentOption);

            // Calculate amounts using strategy
            $calculations = $paymentStrategy->calculate($order->total_amount);

            // Create invoice
            $invoice = $this->invoiceRepository->create([
                'order_id' => $order->id,
                'payment_option' => $paymentOption,
                'tax_amount' => $calculations['tax_amount'],
                'service_charge_amount' => $calculations['service_charge_amount'],
                'final_amount' => $calculations['final_amount']
            ]);

            // Update order status
            $this->orderRepository->updateStatus($order->id, 'paid');

            return $invoice;
        });
    }

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
     * Get available payment gateways - PayPal, Stripe, and Paymob
     */
    public function getAvailableGateways(): array
    {
        return [
            [
                'id' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Pay securely with PayPal',
                'type' => 'redirect',
                'icon' => 'paypal-icon.png'
            ],
            [
                'id' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Pay with credit/debit card via Stripe',
                'type' => 'inline',
                'icon' => 'stripe-icon.png'
            ],
            [
                'id' => 'paymob',
                'name' => 'Paymob',
                'description' => 'Pay with credit/debit card (Egypt)',
                'type' => 'iframe',
                'icon' => 'paymob-icon.png',
                'methods' => [
                    [
                        'id' => 'card',
                        'name' => 'Credit/Debit Card',
                        'description' => 'Pay with Visa, MasterCard, or Meeza'
                    ],
                    [
                        'id' => 'instapay',
                        'name' => 'InstaPay',
                        'description' => 'Pay with mobile wallet (Vodafone Cash, Orange Cash, etc.)'
                    ]
                ]
            ]
        ];
    }

    /**
     * Process payment with specific gateway using Factory Pattern
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

                // Create invoice
                $invoice = $this->invoiceRepository->create([
                    'order_id' => $order->id,
                    'payment_option' => $paymentOption,
                    'tax_amount' => $calculations['tax_amount'],
                    'service_charge_amount' => $calculations['service_charge_amount'],
                    'final_amount' => $calculations['final_amount'],
                    'payment_gateway' => $gateway,
                    'payment_status' => 'pending'
                ]);

                // Process payment with selected gateway using Factory Pattern
                $gatewayResult = $this->processWithGateway($gateway, $calculations['final_amount'], $paymentData, $order, $invoice);

                // Update invoice with payment details
                $invoice->update([
                    'transaction_id' => $gatewayResult['transaction_id'] ?? null,
                    'payment_details' => json_encode($gatewayResult)
                ]);

                // Update order status only if payment is successful and doesn't require redirect
                if ($gatewayResult['success'] && !($gatewayResult['redirect_required'] ?? false)) {
                    $this->orderRepository->updateStatus($order->id, 'paid');
                    $invoice->update(['payment_status' => 'completed']);
                }

                Log::info('Payment processed with gateway', [
                    'gateway' => $gateway,
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'success' => $gatewayResult['success']
                ]);

                return [
                    'invoice' => $invoice,
                    'payment_result' => $gatewayResult
                ];

            } catch (Exception $e) {
                Log::error('Payment processing failed', [
                    'gateway' => $gateway,
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });
    }

    /**
     * Process payment with specific gateway using Factory Pattern (Clean implementation)
     */
    private function processWithGateway(string $gateway, float $amount, array $paymentData, Order $order, Invoice $invoice): array
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

            Log::info('Gateway payment creation result', [
                'gateway' => $gateway,
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('Gateway payment processing failed', [
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
     * Handle payment callback from any gateway using Factory Pattern
     */
    public function handlePaymentCallback(string $gateway, $request): array
    {
        try {
            // Use Factory Pattern to get the appropriate gateway
            $paymentGateway = PaymentGatewayFactory::make($gateway);

            // Handle callback using the gateway
            $result = $paymentGateway->handleCallback($request);

            Log::info('Payment callback handled', [
                'gateway' => $gateway,
                'success' => $result['success'],
                'transaction_id' => $result['transaction_id'] ?? null
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
                'payment_method' => $gateway,
                'verified' => false
            ];
        }
    }
}
