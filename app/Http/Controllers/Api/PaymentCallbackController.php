<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\PaymentService;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * PayPal success callback handler - CRITICAL: This is where payment capture happens
     *
     * GET /api/payment/paypal/success?token=xxx&PayerID=yyy
     */
    public function paypalSuccess(Request $request): JsonResponse|RedirectResponse
    {
        try {
            // Extract PayPal order ID (token) from the request
            $paypalOrderId = $request->query('token');
            $payerId = $request->query('PayerID');

            // Validate required parameters
            if (!$paypalOrderId) {
                Log::warning('PayPal success callback missing token', $request->all());
                return $this->handlePaymentError('Missing PayPal order ID (token)', 400);
            }

            if (!$payerId) {
                Log::warning('PayPal success callback missing PayerID', $request->all());
                return $this->handlePaymentError('Missing PayPal Payer ID', 400);
            }

            Log::info('PayPal success callback initiated', [
                'paypal_order_id' => $paypalOrderId,
                'payer_id' => $payerId
            ]);

            // Find the invoice using transaction_id (PayPal order ID)
            $invoice = Invoice::where('transaction_id', $paypalOrderId)->first();

            if (!$invoice) {
                Log::error('Invoice not found for PayPal transaction', [
                    'paypal_order_id' => $paypalOrderId
                ]);
                return $this->handlePaymentError('Payment record not found', 404);
            }

            // Verify invoice is in pending status
            if ($invoice->payment_status !== 'pending') {
                Log::warning('PayPal callback for non-pending invoice', [
                    'invoice_id' => $invoice->id,
                    'current_status' => $invoice->payment_status,
                    'paypal_order_id' => $paypalOrderId
                ]);

                if ($invoice->payment_status === 'completed') {
                    return $this->handlePaymentSuccess($invoice, 'Payment already completed');
                }

                return $this->handlePaymentError('Invalid payment status', 400);
            }

            // Capture the payment through PaymentService
            $captureResult = $this->paymentService->handlePaymentCallback('paypal', $request);

            if (!$captureResult['success']) {
                Log::error('PayPal payment capture failed', [
                    'paypal_order_id' => $paypalOrderId,
                    'invoice_id' => $invoice->id,
                    'error' => $captureResult['error'] ?? 'Unknown error'
                ]);

                return $this->handlePaymentError(
                    $captureResult['error'] ?? 'Payment capture failed',
                    400
                );
            }

            // Update payment status in database
            $updateSuccess = $this->paymentService->updatePaymentStatus(
                $paypalOrderId,
                $captureResult['status'] ?? 'completed',
                $captureResult
            );

            if (!$updateSuccess) {
                Log::error('Failed to update payment status after successful capture', [
                    'paypal_order_id' => $paypalOrderId,
                    'invoice_id' => $invoice->id
                ]);
                return $this->handlePaymentError('Failed to update payment status', 500);
            }

            // Refresh invoice to get updated status
            $invoice->refresh();

            Log::info('PayPal payment completed successfully', [
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'paypal_order_id' => $paypalOrderId,
                'amount' => $captureResult['amount'] ?? $invoice->final_amount
            ]);

            return $this->handlePaymentSuccess($invoice, 'Payment completed successfully');

        } catch (\Exception $e) {
            Log::error('PayPal success callback exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return $this->handlePaymentError('Payment processing error', 500);
        }
    }

    /**
     * PayPal cancel callback handler
     *
     * GET /api/payment/paypal/cancel?token=xxx
     */
    public function paypalCancel(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $paypalOrderId = $request->query('token');

            Log::info('PayPal payment cancelled', [
                'paypal_order_id' => $paypalOrderId,
                'request_data' => $request->all()
            ]);

            // Update invoice status if found
            if ($paypalOrderId) {
                $invoice = Invoice::where('transaction_id', $paypalOrderId)->first();

                if ($invoice && $invoice->payment_status === 'pending') {
                    $this->paymentService->updatePaymentStatus(
                        $paypalOrderId,
                        'cancelled',
                        ['cancelled_at' => now()->toISOString(), 'reason' => 'User cancelled']
                    );

                    Log::info('Invoice status updated to cancelled', [
                        'invoice_id' => $invoice->id,
                        'order_id' => $invoice->order_id
                    ]);
                }
            }

            return $this->apiResponse(
                200,
                'Payment was cancelled by user',
                null,
                [
                    'status' => 'cancelled',
                    'paypal_order_id' => $paypalOrderId,
                    'message' => 'You can try again or choose a different payment method'
                ]
            );

        } catch (\Exception $e) {
            Log::error('PayPal cancel callback error', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Payment cancellation error', $e->getMessage());
        }
    }

    /**
     * Universal payment callback handler using Factory Pattern
     *
     * POST /api/payment/{gateway}/callback
     */
    public function handleCallback(Request $request, string $gateway): JsonResponse
    {
        try {
            Log::info('Payment callback received', [
                'gateway' => $gateway,
                'request_data' => $request->all()
            ]);

            // Validate gateway
            if (!in_array($gateway, ['paypal'])) {
                return $this->apiResponse(400, 'Unsupported payment gateway');
            }

            // Handle callback using PaymentService
            $result = $this->paymentService->handlePaymentCallback($gateway, $request);

            if ($result['success']) {
                // Update payment status
                if (isset($result['transaction_id'])) {
                    $this->paymentService->updatePaymentStatus(
                        $result['transaction_id'],
                        $result['status'] ?? 'completed',
                        $result
                    );
                }

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
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Payment callback error', $e->getMessage());
        }
    }

    /**
     * Verify payment status endpoint
     *
     * GET /api/payment/{gateway}/verify/{transactionId}
     */
    public function verifyPayment(string $gateway, string $transactionId): JsonResponse
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
            Log::error('Payment verification error', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment verification error', $e->getMessage());
        }
    }

    /**
     * Handle successful payment response
     */
    private function handlePaymentSuccess(Invoice $invoice, string $message): JsonResponse
    {
        return $this->apiResponse(
            200,
            $message,
            null,
            [
                'status' => 'completed',
                'invoice_id' => $invoice->id,
                'order_id' => $invoice->order_id,
                'payment_status' => $invoice->payment_status,
                'transaction_id' => $invoice->transaction_id,
                'amount' => $invoice->final_amount,
                'gateway' => $invoice->payment_gateway
            ]
        );
    }

    /**
     * Handle payment error response
     */
    private function handlePaymentError(string $message, int $statusCode = 400): JsonResponse
    {
        return $this->apiResponse(
            $statusCode,
            'Payment failed',
            $message,
            [
                'status' => 'failed',
                'can_retry' => $statusCode < 500 // Allow retry for client errors, not server errors
            ]
        );
    }
}
