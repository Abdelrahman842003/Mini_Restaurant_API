<?php

namespace App\Http\Services\PaymentStrategies;

use App\Http\Interfaces\PaymentGatewayInterface;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaymobPaymentStrategy implements PaymentGatewayInterface
{
    private string $apiKey;
    private string $integrationId;
    private string $instapayIntegrationId;
    private string $hmacSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.paymob.api_key');
        $this->integrationId = config('services.paymob.integration_id');
        $this->instapayIntegrationId = config('services.paymob.instapay_integration_id');
        $this->hmacSecret = config('services.paymob.hmac_secret');
        $this->baseUrl = 'https://accept.paymob.com/api';
    }

    public function processPayment(Order $order, array $paymentData = []): array
    {
        try {
            $paymentMethod = $paymentData['payment_method'] ?? 'card';

            if ($paymentMethod === 'instapay') {
                return $this->processInstapayPayment($order, $paymentData);
            } else {
                return $this->processCardPayment($order, $paymentData);
            }
        } catch (\Exception $e) {
            Log::error('Paymob payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            throw new \Exception('Payment processing failed: ' . $e->getMessage());
        }
    }

    private function processCardPayment(Order $order, array $paymentData): array
    {
        // 1. احصل على authentication token
        $authToken = $this->getAuthToken();

        // 2. أنشئ order في Paymob
        $paymobOrder = $this->createPaymobOrder($authToken, $order);

        // 3. احصل على payment key
        $paymentKey = $this->getPaymentKey($authToken, $paymobOrder['id'], $order, $this->integrationId);

        return [
            'success' => true,
            'payment_method' => 'paymob_card',
            'redirect_required' => true,
            'iframe_url' => "https://accept.paymob.com/api/acceptance/iframes/iframe_id?payment_token={$paymentKey}",
            'payment_key' => $paymentKey,
            'paymob_order_id' => $paymobOrder['id'],
            'gateway_response' => [
                'payment_key' => $paymentKey,
                'order_id' => $paymobOrder['id']
            ]
        ];
    }

    private function processInstapayPayment(Order $order, array $paymentData): array
    {
        $mobileNumber = $paymentData['mobile_number'] ?? null;

        if (!$mobileNumber) {
            throw new \Exception('Mobile number is required for Instapay');
        }

        // 1. احصل على authentication token
        $authToken = $this->getAuthToken();

        // 2. أنشئ order في Paymob
        $paymobOrder = $this->createPaymobOrder($authToken, $order);

        // 3. احصل على payment key للـ Instapay
        $paymentKey = $this->getPaymentKey($authToken, $paymobOrder['id'], $order, $this->instapayIntegrationId);

        // 4. قم بعملية Instapay payment
        $instapayResult = $this->initiateInstapayPayment($paymentKey, $mobileNumber);

        return [
            'success' => true,
            'payment_method' => 'instapay',
            'redirect_required' => false,
            'payment_key' => $paymentKey,
            'paymob_order_id' => $paymobOrder['id'],
            'instapay_url' => $instapayResult['redirect_url'] ?? null,
            'gateway_response' => $instapayResult
        ];
    }

    private function getAuthToken(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => $this->apiKey
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get Paymob auth token');
        }

        return $response->json('token');
    }

    private function createPaymobOrder(string $authToken, Order $order): array
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $authToken,
            'delivery_needed' => 'false',
            'amount_cents' => intval($order->total_amount * 100), // Convert to cents
            'currency' => 'EGP',
            'items' => $this->getOrderItems($order)
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to create Paymob order');
        }

        return $response->json();
    }

    private function getPaymentKey(string $authToken, int $paymobOrderId, Order $order, string $integrationId): string
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token' => $authToken,
            'amount_cents' => intval($order->total_amount * 100),
            'expiration' => 3600, // 1 hour
            'order_id' => $paymobOrderId,
            'billing_data' => $this->getBillingData($order),
            'currency' => 'EGP',
            'integration_id' => $integrationId
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to get payment key');
        }

        return $response->json('token');
    }

    private function initiateInstapayPayment(string $paymentKey, string $mobileNumber): array
    {
        $response = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $mobileNumber,
                'subtype' => 'WALLET'
            ],
            'payment_token' => $paymentKey
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to initiate Instapay payment');
        }

        return $response->json();
    }

    private function getOrderItems(Order $order): array
    {
        // إذا كان لديك order items
        if ($order->orderItems && $order->orderItems->count() > 0) {
            return $order->orderItems->map(function ($item) {
                return [
                    'name' => $item->menuItem->name ?? 'Item',
                    'amount_cents' => intval($item->price * 100),
                    'description' => $item->menuItem->description ?? 'Restaurant item',
                    'quantity' => $item->quantity
                ];
            })->toArray();
        }

        // fallback إذا لم تكن هناك items
        return [
            [
                'name' => 'Restaurant Order #' . $order->id,
                'amount_cents' => intval($order->total_amount * 100),
                'description' => 'Restaurant order payment',
                'quantity' => 1
            ]
        ];
    }

    private function getBillingData(Order $order): array
    {
        $user = $order->user;

        return [
            'apartment' => 'NA',
            'email' => $user->email,
            'floor' => 'NA',
            'first_name' => $user->name ?? 'Customer',
            'street' => 'NA',
            'building' => 'NA',
            'phone_number' => $user->phone ?? '+201000000000',
            'shipping_method' => 'NA',
            'postal_code' => 'NA',
            'city' => 'Cairo',
            'country' => 'EG',
            'last_name' => 'Customer',
            'state' => 'Cairo'
        ];
    }

    public function verifyCallback(array $callbackData): bool
    {
        $hmacData = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order',
            'owner',
            'pending',
            'source_data_pan',
            'source_data_sub_type',
            'source_data_type',
            'success'
        ];

        $hmacString = '';
        foreach ($hmacData as $key) {
            $value = $callbackData[$key] ?? '';
            if (is_array($value)) {
                $value = $value['id'] ?? '';
            }
            $hmacString .= $value;
        }

        $calculatedHmac = hash_hmac('sha512', $hmacString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $callbackData['hmac'] ?? '');
    }

    public function getGatewayName(): string
    {
        return 'paymob';
    }
}
