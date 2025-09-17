<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\PaymentService;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Universal payment callback handler using Factory Pattern
     *
     * POST /api/payment/{gateway}/callback
     */
    public function handleCallback(Request $request, string $gateway)
    {
        try {
            // Use PaymentService with Factory Pattern to handle callback
            $result = $this->paymentService->handlePaymentCallback($gateway, $request);

            if ($result['success']) {
                // Update invoice and order status
                $this->updatePaymentStatus($result);

                return $this->apiResponse(
                    200,
                    'Payment callback processed successfully',
                    null,
                    $result
                );
            } else {
                return $this->apiResponse(
                    400,
                    'Payment callback processing failed',
                    $result['error'] ?? 'Unknown error',
                    $result
                );
            }

        } catch (\Exception $e) {
            Log::error('Payment callback error', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment callback error', $e->getMessage());
        }
    }

    /**
     * PayPal success redirect handler
     *
     * GET /api/payment/paypal/success?paymentId=xxx&PayerID=yyy
     */
    public function paypalSuccess(Request $request)
    {
        try {
            $paymentId = $request->query('paymentId');
            $payerId = $request->query('PayerID');

            if (!$paymentId || !$payerId) {
                return $this->apiResponse(400, 'Missing payment parameters');
            }

            // Create a mock request for the callback handler
            $callbackRequest = Request::create('', 'POST', [
                'paymentId' => $paymentId,
                'PayerID' => $payerId
            ]);

            $result = $this->paymentService->handlePaymentCallback('paypal', $callbackRequest);

            if ($result['success']) {
                $this->updatePaymentStatus($result);

                return $this->apiResponse(
                    200,
                    'Payment completed successfully',
                    null,
                    [
                        'payment_id' => $paymentId,
                        'status' => 'completed',
                        'gateway' => 'paypal'
                    ]
                );
            } else {
                return $this->apiResponse(400, 'Payment execution failed', $result['error']);
            }

        } catch (\Exception $e) {
            Log::error('PayPal success callback error: ' . $e->getMessage());
            return $this->apiResponse(500, 'Payment callback error', $e->getMessage());
        }
    }

    /**
     * PayPal cancel handler
     *
     * GET /api/payment/paypal/cancel?paymentId=xxx
     */
    public function paypalCancel(Request $request)
    {
        try {
            $paymentId = $request->query('paymentId');

            Log::info('PayPal payment cancelled', ['payment_id' => $paymentId]);

            // Update invoice status to cancelled
            $invoice = Invoice::where('transaction_id', $paymentId)->first();
            if ($invoice) {
                $invoice->update(['payment_status' => 'cancelled']);
                $invoice->order->update(['status' => 'cancelled']);
            }

            return $this->apiResponse(
                200,
                'Payment was cancelled by user',
                null,
                [
                    'payment_id' => $paymentId,
                    'status' => 'cancelled',
                    'gateway' => 'paypal'
                ]
            );
        } catch (\Exception $e) {
            Log::error('PayPal cancel callback error: ' . $e->getMessage());
            return $this->apiResponse(500, 'Payment cancel error', $e->getMessage());
        }
    }

    /**
     * Stripe webhook handler
     *
     * POST /api/webhooks/stripe
     */
    public function stripeWebhook(Request $request)
    {
        try {
            $result = $this->paymentService->handlePaymentCallback('stripe', $request);

            if ($result['success']) {
                $this->updatePaymentStatus($result);
                return response('OK', 200);
            } else {
                Log::warning('Stripe webhook processing failed', $result);
                return response('Webhook processing failed', 400);
            }

        } catch (\Exception $e) {
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response('Webhook error', 400);
        }
    }

    /**
     * Paymob callback handler
     *
     * POST /api/payment/paymob/callback
     */
    public function paymobCallback(Request $request)
    {
        try {
            $result = $this->paymentService->handlePaymentCallback('paymob', $request);

            if ($result['success']) {
                $this->updatePaymentStatus($result);

                return $this->apiResponse(
                    200,
                    'Paymob callback processed successfully',
                    null,
                    $result
                );
            } else {
                return $this->apiResponse(
                    400,
                    'Paymob callback processing failed',
                    $result['error'] ?? 'Unknown error'
                );
            }

        } catch (\Exception $e) {
            Log::error('Paymob callback error: ' . $e->getMessage());
            return $this->apiResponse(500, 'Paymob callback error', $e->getMessage());
        }
    }

    /**
     * Verify payment status
     *
     * GET /api/payment/{gateway}/verify/{transactionId}
     */
    public function verifyPayment(string $gateway, string $transactionId)
    {
        try {
            $result = $this->paymentService->verifyPaymentStatus($gateway, $transactionId);

            return $this->apiResponse(
                200,
                $result['success'] ? 'Payment verification completed' : 'Payment verification failed',
                $result['success'] ? null : ($result['error'] ?? 'Verification failed'),
                $result
            );

        } catch (\Exception $e) {
            Log::error('Payment verification error: ' . $e->getMessage());
            return $this->apiResponse(500, 'Payment verification error', $e->getMessage());
        }
    }

    /**
     * Update payment status in database
     */
    private function updatePaymentStatus(array $result): void
    {
        if (!isset($result['transaction_id'])) {
            return;
        }

        $invoice = Invoice::where('transaction_id', $result['transaction_id'])->first();
        if (!$invoice) {
            Log::warning('Invoice not found for transaction', ['transaction_id' => $result['transaction_id']]);
            return;
        }

        $paymentStatus = match ($result['status']) {
            'completed' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => 'pending'
        };

        $orderStatus = match ($result['status']) {
            'completed' => 'paid',
            'failed' => 'payment_failed',
            'cancelled' => 'cancelled',
            default => 'pending'
        };

        // Update invoice
        $invoice->update([
            'payment_status' => $paymentStatus,
            'payment_details' => json_encode(array_merge(
                json_decode($invoice->payment_details, true) ?? [],
                [
                    'callback_result' => $result,
                    'updated_at' => now()->toISOString()
                ]
            ))
        ]);

        // Update order
        $invoice->order->update(['status' => $orderStatus]);

        Log::info('Payment status updated', [
            'invoice_id' => $invoice->id,
            'order_id' => $invoice->order_id,
            'payment_status' => $paymentStatus,
            'order_status' => $orderStatus
        ]);
    }
}
