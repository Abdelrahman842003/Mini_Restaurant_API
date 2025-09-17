<?php

namespace App\Http\Services\PaymentGateways;

use App\Http\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Exception;

class StripeGateway implements PaymentGatewayInterface
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Create a payment with Stripe
     */
    public function createPayment(array $data): array
    {
        try {
            $this->validatePaymentData($data);

            $paymentIntent = PaymentIntent::create([
                'amount' => intval($data['amount'] * 100), // Convert to cents
                'currency' => $data['currency'] ?? 'usd',
                'metadata' => $data['metadata'] ?? [],
                'description' => $data['description'] ?? 'Restaurant Payment',
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'confirmation_method' => 'manual',
                'confirm' => false,
            ]);

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'payment_method' => 'stripe',
                'amount' => $data['amount'],
                'status' => $paymentIntent->status,
                'redirect_required' => false,
                'requires_action' => $paymentIntent->status === 'requires_action',
                'next_action' => $paymentIntent->next_action,
            ];
        } catch (Exception $e) {
            Log::error('Stripe payment creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'stripe'
            ];
        }
    }

    /**
     * Handle Stripe webhook callback
     */
    public function handleCallback(Request $request): array
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('Stripe-Signature');
            $webhookSecret = config('services.stripe.webhook_secret');

            $event = Webhook::constructEvent($payload, $signature, $webhookSecret);

            Log::info('Stripe webhook received', [
                'event_type' => $event['type'],
                'event_id' => $event['id']
            ]);

            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event['data']['object'];
                    return [
                        'success' => true,
                        'status' => 'completed',
                        'transaction_id' => $paymentIntent['id'],
                        'amount' => $paymentIntent['amount'] / 100,
                        'currency' => $paymentIntent['currency'],
                        'payment_method' => 'stripe',
                        'metadata' => $paymentIntent['metadata'] ?? []
                    ];

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event['data']['object'];
                    return [
                        'success' => false,
                        'status' => 'failed',
                        'transaction_id' => $paymentIntent['id'],
                        'error' => $paymentIntent['last_payment_error']['message'] ?? 'Payment failed',
                        'payment_method' => 'stripe'
                    ];

                case 'payment_intent.canceled':
                    $paymentIntent = $event['data']['object'];
                    return [
                        'success' => false,
                        'status' => 'cancelled',
                        'transaction_id' => $paymentIntent['id'],
                        'payment_method' => 'stripe'
                    ];

                default:
                    return [
                        'success' => true,
                        'status' => 'unhandled_event',
                        'event_type' => $event['type']
                    ];
            }
        } catch (Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'stripe'
            ];
        }
    }

    /**
     * Verify payment status directly from Stripe API
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $paymentIntent = PaymentIntent::retrieve($transactionId);

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'payment_method' => 'stripe',
                'verified' => true,
                'metadata' => $paymentIntent->metadata->toArray()
            ];
        } catch (Exception $e) {
            Log::error('Stripe payment verification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'stripe',
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

        if (isset($paymentData['currency']) && !in_array($paymentData['currency'], ['usd', 'eur', 'gbp'])) {
            throw new Exception('Unsupported currency');
        }

        return true;
    }

    /**
     * Get gateway name
     */
    public function getGatewayName(): string
    {
        return 'stripe';
    }

    /**
     * Create Payment Intent for frontend (additional method)
     */
    public function createPaymentIntent(float $amount, array $options = []): array
    {
        return $this->createPayment(array_merge([
            'amount' => $amount
        ], $options));
    }

    /**
     * Confirm Payment Intent (additional method)
     */
    public function confirmPaymentIntent(string $paymentIntentId, string $paymentMethodId = null): array
    {
        try {
            $params = ['confirmation_method' => 'manual', 'confirm' => true];

            if ($paymentMethodId) {
                $params['payment_method'] = $paymentMethodId;
            }

            $paymentIntent = PaymentIntent::retrieve($paymentIntentId);
            $paymentIntent = $paymentIntent->confirm($params);

            return [
                'success' => true,
                'transaction_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
                'amount' => $paymentIntent->amount / 100,
                'requires_action' => $paymentIntent->status === 'requires_action',
                'next_action' => $paymentIntent->next_action,
                'payment_method' => 'stripe'
            ];
        } catch (Exception $e) {
            Log::error('Stripe payment confirmation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'stripe'
            ];
        }
    }
}
