<?php

namespace App\Http\Services\PaymentGateways;

use App\Http\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Exception;

class PayPalGateway implements PaymentGatewayInterface
{
    private PayPalClient $paypal;
    private bool $testMode = false;

    public function __construct()
    {
        $this->paypal = new PayPalClient;

        // Get PayPal configuration from config file
        $config = config('paypal');

        // Validate that configuration exists
        if (!$config) {
            throw new Exception('PayPal configuration not found. Please ensure config/paypal.php exists.');
        }

        // Validate required configuration based on mode
        $mode = $config['mode'] ?? 'sandbox';
        $clientId = $config[$mode]['client_id'] ?? '';
        $clientSecret = $config[$mode]['client_secret'] ?? '';

        // For development: Allow test mode with mock credentials
        if ($mode === 'sandbox' && (empty($clientId) || $clientId === 'your_paypal_client_id_here')) {
            Log::warning('PayPal running in test mode - using mock responses for development');
            $this->testMode = true;
            return;
        }

        if (empty($clientId)) {
            throw new Exception("PayPal client_id missing from the provided configuration. Please add your application client_id.");
        }

        if (empty($clientSecret)) {
            throw new Exception("PayPal client_secret missing from the provided configuration. Please add your application client_secret.");
        }

        Log::info('PayPal configuration loaded', [
            'mode' => $mode,
            'has_client_id' => !empty($clientId),
            'has_client_secret' => !empty($clientSecret)
        ]);

        $this->paypal->setApiCredentials($config);

        try {
            $accessToken = $this->paypal->getAccessToken();
            Log::info('PayPal access token obtained successfully');
        } catch (Exception $e) {
            Log::error('PayPal initialization failed', [
                'error' => $e->getMessage(),
                'config_mode' => $mode
            ]);
            throw new Exception('PayPal initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Create a payment with PayPal
     */
    public function createPayment(array $data): array
    {
        try {
            $this->validatePaymentData($data);

            // Test mode: Return mock successful response for development
            if ($this->testMode) {
                Log::info('PayPal test mode - returning mock successful response');

                $mockOrderId = 'MOCK_ORDER_' . uniqid();
                $mockApprovalUrl = config('app.url') . '/mock-paypal-approval?token=' . $mockOrderId;

                return [
                    'success' => true,
                    'transaction_id' => $mockOrderId,
                    'approval_url' => $mockApprovalUrl,
                    'payment_method' => 'paypal',
                    'amount' => $data['amount'],
                    'currency' => $data['currency'] ?? 'USD',
                    'status' => 'created',
                    'redirect_required' => true,
                    'paypal_order' => [
                        'id' => $mockOrderId,
                        'status' => 'CREATED',
                        'links' => [
                            [
                                'href' => $mockApprovalUrl,
                                'rel' => 'approve',
                                'method' => 'GET'
                            ]
                        ]
                    ]
                ];
            }

            // Log payment creation attempt
            Log::info('Creating PayPal order', [
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'invoice_number' => $data['invoice_number'] ?? 'unknown'
            ]);

            $order = [
                'intent' => 'CAPTURE',
                'application_context' => [
                    'return_url' => $data['return_url'] ?? config('paypal.return_url'),
                    'cancel_url' => $data['cancel_url'] ?? config('paypal.cancel_url'),
                    'brand_name' => config('app.name', 'Restaurant'),
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW'
                ],
                'purchase_units' => [
                    [
                        'reference_id' => (string)($data['invoice_number'] ?? uniqid()),
                        'description' => $data['description'] ?? 'Restaurant Order Payment',
                        'amount' => [
                            'currency_code' => $data['currency'] ?? 'USD',
                            'value' => number_format((float)$data['amount'], 2, '.', '')
                        ]
                    ]
                ]
            ];

            // Log the order structure being sent to PayPal
            Log::info('PayPal order request', ['order_structure' => $order]);

            $response = $this->paypal->createOrder($order);

            // Enhanced logging of PayPal response
            Log::info('PayPal API raw response', [
                'response_type' => gettype($response),
                'response_content' => $response,
                'is_array' => is_array($response),
                'response_keys' => is_array($response) ? array_keys($response) : 'not_array'
            ]);

            // Enhanced response validation with better error reporting
            if (!$response) {
                throw new Exception('PayPal API returned null response. Check your PayPal credentials.');
            }

            if (!is_array($response)) {
                throw new Exception('PayPal API returned non-array response: ' . gettype($response) . ' - ' . print_r($response, true));
            }

            // Check for PayPal API error response
            if (isset($response['error'])) {
                $errorMessage = 'PayPal API Error: ' . ($response['error']['message'] ?? 'Unknown error');
                Log::error('PayPal API error response', $response);
                throw new Exception($errorMessage);
            }

            if (!isset($response['id']) || !isset($response['status'])) {
                Log::error('PayPal order creation failed - missing required fields', [
                    'response' => $response,
                    'required_fields' => ['id', 'status'],
                    'received_keys' => array_keys($response)
                ]);
                throw new Exception('PayPal order creation failed - missing id or status in response');
            }

            // Find approval URL
            $approvalUrl = null;
            if (isset($response['links']) && is_array($response['links'])) {
                foreach ($response['links'] as $link) {
                    if (isset($link['rel']) && $link['rel'] === 'approve') {
                        $approvalUrl = $link['href'];
                        break;
                    }
                }
            }

            if (!$approvalUrl) {
                Log::error('PayPal approval URL not found', [
                    'response_links' => $response['links'] ?? 'no_links',
                    'full_response' => $response
                ]);
                throw new Exception('PayPal approval URL not found in response');
            }

            Log::info('PayPal order created successfully', [
                'order_id' => $response['id'],
                'status' => $response['status'],
                'has_approval_url' => !empty($approvalUrl)
            ]);

            return [
                'success' => true,
                'transaction_id' => $response['id'],
                'approval_url' => $approvalUrl,
                'payment_method' => 'paypal',
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'status' => 'created',
                'redirect_required' => true,
                'paypal_order' => $response
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment creation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => collect($data)->except(['client_id', 'client_secret'])->toArray() // Hide sensitive data
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }

    /**
     * Handle PayPal callback
     */
    public function handleCallback(Request $request): array
    {
        try {
            $orderId = $request->query('token') ?? $request->input('paymentId') ?? $request->input('orderID');

            if (!$orderId) {
                return [
                    'success' => false,
                    'error' => 'Missing PayPal order ID',
                    'payment_method' => 'paypal'
                ];
            }

            Log::info('PayPal callback processing', ['order_id' => $orderId]);

            // Capture the payment
            $response = $this->paypal->capturePaymentOrder($orderId);

            Log::info('PayPal capture response', [
                'order_id' => $orderId,
                'status' => $response['status'] ?? 'unknown',
                'response_keys' => array_keys($response ?? [])
            ]);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                $captureId = $response['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
                $amount = $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0;

                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $orderId,
                    'capture_id' => $captureId,
                    'amount' => floatval($amount),
                    'payment_method' => 'paypal',
                    'paypal_response' => $response
                ];
            }

            return [
                'success' => false,
                'status' => $response['status'] ?? 'failed',
                'error' => 'Payment capture failed',
                'payment_method' => 'paypal',
                'paypal_response' => $response
            ];

        } catch (Exception $e) {
            Log::error('PayPal callback error', [
                'error' => $e->getMessage(),
                'order_id' => $orderId ?? 'unknown'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }

    /**
     * Verify payment status directly from PayPal API
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $response = $this->paypal->showOrderDetails($transactionId);

            if (isset($response['id'])) {
                $status = $response['status'];
                $amount = 0;

                if (isset($response['purchase_units'][0]['payments']['captures'][0])) {
                    $amount = $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'];
                }

                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'status' => strtolower($status),
                    'amount' => floatval($amount),
                    'payment_method' => 'paypal',
                    'verified' => true,
                    'payment_details' => $response
                ];
            }

            throw new Exception('Invalid PayPal order response');

        } catch (Exception $e) {
            Log::error('PayPal payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal',
                'verified' => false
            ];
        }
    }

    /**
     * Validate payment data before processing
     */
    public function validatePaymentData(array $paymentData): bool
    {
        if (!isset($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new Exception('Invalid amount provided: ' . ($paymentData['amount'] ?? 'not_set'));
        }

        if ($paymentData['amount'] > 10000) {
            throw new Exception('Amount exceeds maximum limit for PayPal payments');
        }

        $supportedCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'JPY'];
        $currency = $paymentData['currency'] ?? 'USD';

        if (!in_array(strtoupper($currency), $supportedCurrencies)) {
            throw new Exception('Unsupported currency for PayPal: ' . $currency);
        }

        return true;
    }

    /**
     * Get gateway name
     */
    public function getGatewayName(): string
    {
        return 'paypal';
    }

    /**
     * Cancel PayPal payment
     */
    public function cancelPayment(string $orderId): array
    {
        try {
            Log::info('PayPal payment cancelled by user', ['order_id' => $orderId]);

            return [
                'success' => true,
                'status' => 'cancelled',
                'transaction_id' => $orderId,
                'payment_method' => 'paypal'
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment cancellation error', [
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }

    /**
     * Refund PayPal payment
     */
    public function refundPayment(string $captureId, float $amount = null): array
    {
        try {
            $refundData = [];
            if ($amount) {
                $refundData['amount'] = [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => 'USD'
                ];
            }

            $response = $this->paypal->refundCapturedPayment($captureId, 'Refund requested', $refundData ?? []);

            if (isset($response['status']) && $response['status'] === 'COMPLETED') {
                return [
                    'success' => true,
                    'status' => 'refunded',
                    'refund_id' => $response['id'],
                    'amount' => $response['amount']['value'] ?? $amount,
                    'payment_method' => 'paypal'
                ];
            }

            return [
                'success' => false,
                'error' => 'Refund failed',
                'payment_method' => 'paypal'
            ];

        } catch (Exception $e) {
            Log::error('PayPal refund error', [
                'capture_id' => $captureId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }
}
