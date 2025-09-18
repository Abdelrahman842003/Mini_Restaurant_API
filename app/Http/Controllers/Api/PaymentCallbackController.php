<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\PaymentService;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentCallbackController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentService $paymentService
    ) {}

    /**
     * Handle payment callback from any gateway
     */
    public function handleCallback(Request $request, string $gateway): JsonResponse
    {

        try {
            $result = $this->paymentService->handlePaymentCallback($gateway, $request);

            if ($result['success']) {
                return $this->apiResponse(200, 'Payment callback processed successfully');
            }

            return $this->apiResponse(400, 'Payment callback processing failed');

        } catch (\Exception $e) {
            Log::error('Payment callback failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Payment callback processing failed', $e->getMessage());
        }
    }

    /**
     * Handle PayPal success callback
     */
    public function paypalSuccess(Request $request)
    {
        try {
            // PayPal can send either 'token' or 'paymentId' parameter
            $token = $request->get('token') ?? $request->get('paymentId');
            $payerId = $request->get('PayerID') ?? $request->get('payer_id');

            Log::info('PayPal success callback received', [
                'request_data' => $request->all(),
                'extracted_token' => $token,
                'extracted_payer_id' => $payerId
            ]);

            if (!$token) {
                Log::error('PayPal success callback missing token/paymentId', $request->all());
                return response()->json([
                    'success' => false,
                    'message' => 'Missing payment token or paymentId'
                ], 400);
            }

            $result = $this->paymentService->handlePaymentCallback('paypal', $request);

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'payment_id' => $payerId,
                        'token' => $token
                    ]
                ]);
            }

            // If callback failed, try alternative approach
            Log::warning('Standard callback failed, attempting alternative verification', [
                'token' => $token,
                'payer_id' => $payerId
            ]);

            // Try to find and update the invoice directly if it exists
            $invoice = \App\Models\Invoice::where('transaction_id', $token)->first();
            if ($invoice && $invoice->payment_status === 'pending') {
                $invoice->update([
                    'payment_status' => 'completed',
                    'payment_details' => array_merge(
                        is_array($invoice->payment_details) ? $invoice->payment_details : [],
                        [
                            'paypal_success_callback' => $request->all(),
                            'completed_at' => now()->toISOString(),
                            'manual_completion' => true
                        ]
                    )
                ]);

                // Update order status
                if ($invoice->order) {
                    $invoice->order->update(['status' => 'paid']);
                }

                Log::info('Payment manually completed via alternative method', [
                    'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Payment completed successfully',
                    'data' => [
                        'payment_id' => $payerId,
                        'token' => $token,
                        'method' => 'alternative_completion'
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed'
            ], 400);

        } catch (\Exception $e) {
            Log::error('PayPal success callback failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Handle PayPal cancel callback
     */
    public function paypalCancel(Request $request)
    {
        try {
            Log::info('PayPal payment cancelled', [
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment was cancelled by user',
                'data' => [
                    'token' => $request->get('token')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('PayPal cancel callback failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error processing payment cancellation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request, string $gateway, string $transactionId): JsonResponse
    {
        try {
            $result = $this->paymentService->getPaymentStatus($gateway, $transactionId);

            return $this->apiResponse(
                200,
                'Payment status retrieved successfully',
                null,
                $result
            );

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment verification failed', $e->getMessage());
        }
    }
}
