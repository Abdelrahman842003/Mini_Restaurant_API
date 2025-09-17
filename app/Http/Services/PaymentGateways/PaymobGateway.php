<?php

namespace App\Http\Services\PaymentGateways;

use App\Http\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymobGateway implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $integrationId;
    private string $iframeId;
    private string $instapayIntegrationId;
    private string $hmacSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
        $this->iframeId = config('services.paymob.iframe_id');
        $this->instapayIntegrationId = config('services.paymob.instapay_integration_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
        $this->baseUrl = config('services.paymob.base_url', 'https://accept.paymob.com/api');
    }

    /**
     * Create a payment with Paymob (with InstaPay support)
     */
    public function createPayment(array $data): array
    {
        try {
            $this->validatePaymentData($data);

            // Step 1: Authentication Request
            $authToken = $this->getAuthToken();
            if (!$authToken) {
                throw new Exception('Failed to get authentication token from Paymob');
            }

            // Step 2: Order Registration Request
            $paymobOrder = $this->registerOrder($authToken, $data);
            if (!$paymobOrder) {
                throw new Exception('Failed to register order with Paymob');
            }

            // Step 3: Payment Key Request (different integration ID for InstaPay)
            $paymentMethod = $data['payment_method'] ?? 'card';
            $integrationId = $paymentMethod === 'instapay' ? $this->instapayIntegrationId : $this->integrationId;

            $paymentKey = $this->getPaymentKey($authToken, $paymobOrder['id'], $integrationId, $data);
            if (!$paymentKey) {
                throw new Exception('Failed to get payment key from Paymob');
            }

            // Prepare response based on payment method
            $response = [
                'success' => true,
                'transaction_id' => $paymobOrder['id'],
                'payment_key' => $paymentKey,
                'paymob_order_id' => $paymobOrder['id'],
                'payment_method' => 'paymob',
                'amount' => $data['amount'],
                'status' => 'pending',
                'redirect_required' => true,
            ];

            if ($paymentMethod === 'instapay') {
                // For InstaPay, create special URL
                $instapayUrl = $this->createInstapayUrl($paymentKey, $data['mobile_number'] ?? '');
                $response['instapay_url'] = $instapayUrl;
                $response['iframe_url'] = null;
            } else {
                // For card payments, use iframe
                $response['iframe_url'] = "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey}";
                $response['instapay_url'] = null;
            }

            return $response;

        } catch (Exception $e) {
            Log::error('Paymob payment creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paymob'
            ];
        }
    }

    /**
     * Handle Paymob callback/webhook
     */
    public function handleCallback(Request $request): array
    {
        try {
            $callbackData = $request->all();

            // Verify HMAC signature for security
            if (!$this->verifyHmacSignature($callbackData)) {
                Log::warning('Paymob callback with invalid HMAC signature', $callbackData);
                return [
                    'success' => false,
                    'error' => 'Invalid HMAC signature',
                    'payment_method' => 'paymob'
                ];
            }

            $transactionId = $callbackData['order']['id'] ?? null;
            $success = ($callbackData['success'] ?? 'false') === 'true';
            $pending = ($callbackData['pending'] ?? 'false') === 'true';

            if ($success && !$pending) {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $transactionId,
                    'amount' => ($callbackData['amount_cents'] ?? 0) / 100,
                    'payment_method' => 'paymob',
                    'paymob_transaction_id' => $callbackData['id'] ?? null,
                    'payment_details' => $callbackData
                ];
            } elseif ($pending) {
                return [
                    'success' => false,
                    'status' => 'pending',
                    'transaction_id' => $transactionId,
                    'payment_method' => 'paymob',
                    'message' => 'Payment is still pending'
                ];
            } else {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'transaction_id' => $transactionId,
                    'error' => 'Payment failed or was declined',
                    'payment_method' => 'paymob'
                ];
            }

        } catch (Exception $e) {
            Log::error('Paymob callback error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paymob'
            ];
        }
    }

    /**
     * Verify payment status directly from Paymob API
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $authToken = $this->getAuthToken();
            if (!$authToken) {
                throw new Exception('Failed to get authentication token');
            }

            $response = Http::get("{$this->baseUrl}/ecommerce/orders/{$transactionId}", [
                'token' => $authToken
            ]);

            if (!$response->successful()) {
                throw new Exception('Failed to verify payment with Paymob API');
            }

            $orderData = $response->json();
            $isPaid = $orderData['paid_amount_cents'] > 0;

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => $isPaid ? 'completed' : 'pending',
                'amount' => ($orderData['amount_cents'] ?? 0) / 100,
                'paid_amount' => ($orderData['paid_amount_cents'] ?? 0) / 100,
                'payment_method' => 'paymob',
                'verified' => true,
                'order_data' => $orderData
            ];

        } catch (Exception $e) {
            Log::error('Paymob payment verification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paymob',
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
            throw new Exception('Invalid amount provided');
        }

        // For InstaPay, mobile number is required
        $paymentMethod = $paymentData['payment_method'] ?? 'card';
        if ($paymentMethod === 'instapay') {
            if (!isset($paymentData['mobile_number']) || empty($paymentData['mobile_number'])) {
                throw new Exception('Mobile number is required for InstaPay');
            }

            // Validate Egyptian mobile number format
            $mobileNumber = $paymentData['mobile_number'];
            if (!preg_match('/^(\+20|0)?1[0-9]{9}$/', $mobileNumber)) {
                throw new Exception('Invalid Egyptian mobile number format');
            }
        }

        return true;
    }

    /**
     * Get gateway name
     */
    public function getGatewayName(): string
    {
        return 'paymob';
    }

    /**
     * Get authentication token from Paymob
     */
    private function getAuthToken(): ?string
    {
        try {
            $response = Http::post("{$this->baseUrl}/auth/tokens", [
                'api_key' => $this->apiKey
            ]);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Failed to get Paymob auth token', ['response' => $response->json()]);
            return null;

        } catch (Exception $e) {
            Log::error('Paymob auth token request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Register order with Paymob
     */
    private function registerOrder(string $authToken, array $data): ?array
    {
        try {
            $orderData = [
                'auth_token' => $authToken,
                'delivery_needed' => 'false',
                'amount_cents' => intval($data['amount'] * 100),
                'currency' => $data['currency'] ?? 'EGP',
                'items' => [[
                    'name' => $data['description'] ?? 'Restaurant Order',
                    'amount_cents' => intval($data['amount'] * 100),
                    'description' => $data['description'] ?? 'Restaurant Payment',
                    'quantity' => 1
                ]]
            ];

            $response = Http::post("{$this->baseUrl}/ecommerce/orders", $orderData);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to register Paymob order', ['response' => $response->json()]);
            return null;

        } catch (Exception $e) {
            Log::error('Paymob order registration failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get payment key from Paymob
     */
    private function getPaymentKey(string $authToken, int $orderId, string $integrationId, array $data): ?string
    {
        try {
            $billingData = [
                'first_name' => $data['customer']['first_name'] ?? 'Customer',
                'last_name' => $data['customer']['last_name'] ?? '',
                'email' => $data['customer']['email'] ?? 'customer@example.com',
                'phone_number' => $data['customer']['phone'] ?? '+201000000000',
                'apartment' => 'NA',
                'floor' => 'NA',
                'street' => 'NA',
                'building' => 'NA',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
                'city' => $data['customer']['city'] ?? 'Cairo',
                'country' => 'EG',
                'state' => 'NA'
            ];

            $paymentKeyData = [
                'auth_token' => $authToken,
                'amount_cents' => intval($data['amount'] * 100),
                'expiration' => 3600, // 1 hour
                'order_id' => $orderId,
                'billing_data' => $billingData,
                'currency' => $data['currency'] ?? 'EGP',
                'integration_id' => $integrationId,
                'lock_order_when_paid' => 'false'
            ];

            $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", $paymentKeyData);

            if ($response->successful()) {
                return $response->json('token');
            }

            Log::error('Failed to get Paymob payment key', ['response' => $response->json()]);
            return null;

        } catch (Exception $e) {
            Log::error('Paymob payment key request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create InstaPay URL for mobile wallet payments
     */
    private function createInstapayUrl(string $paymentKey, string $mobileNumber): string
    {
        return "https://accept.paymob.com/api/acceptance/payments/pay?" . http_build_query([
            'source' => [
                'identifier' => $mobileNumber,
                'subtype' => 'WALLET'
            ],
            'payment_token' => $paymentKey
        ]);
    }

    /**
     * Verify HMAC signature for callback security
     */
    private function verifyHmacSignature(array $data): bool
    {
        if (!isset($data['hmac'])) {
            return false;
        }

        $receivedHmac = $data['hmac'];
        unset($data['hmac']); // Remove hmac from data before calculation

        // Sort data by keys and concatenate values
        ksort($data);
        $concatenatedString = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $concatenatedString .= $value;
        }

        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}
