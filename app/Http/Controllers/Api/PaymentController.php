<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProcessPaymentRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Services\OrderService;
use App\Http\Services\PaymentService;
use App\Http\Traits\ApiResponseTrait;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private PaymentService $paymentService,
        private OrderService $orderService
    ) {}

    /**
     * عرض خيارات الدفع المتاحة
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $paymentMethods = $this->paymentService->getPaymentMethods();

            return $this->apiResponse(
                200,
                'Payment methods retrieved successfully',
                null,
                $paymentMethods
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve payment methods', $e->getMessage());
        }
    }

    /**
     * عرض بوابات الدفع المتاحة مع المعلومات المحدثة
     */
    public function getPaymentGateways(): JsonResponse
    {
        try {
            $gateways = $this->paymentService->getAvailableGateways();

            return $this->apiResponse(
                200,
                'Payment gateways retrieved successfully',
                null,
                $gateways
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve payment gateways', $e->getMessage());
        }
    }

    /**
     * معالجة عملية الدفع لطلب معين
     */
    public function processPayment(ProcessPaymentRequest $request, Order $order): JsonResponse
    {
        try {
            $validated = $request->validated();

            // التحقق من ملكية الطلب للمستخدم المسجل
            if ($order->user_id !== auth()->id()) {
                return $this->apiResponse(404, 'Order not found or unauthorized');
            }

            if ($order->status === 'paid') {
                return $this->apiResponse(400, 'Order is already paid');
            }

            // معالجة الدفع باستخدام الخدمة المحدثة
            $result = $this->paymentService->processPaymentWithGateway(
                $order,
                $validated['payment_option'],
                $validated['payment_gateway'],
                $validated['payment_data'] ?? []
            );

            return $this->apiResponse(
                200,
                'Payment processing initiated successfully',
                null,
                [
                    'invoice' => new InvoiceResource($result['invoice']),
                    'payment_result' => $result['payment_result']
                ]
            );

        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment processing failed', $e->getMessage());
        }
    }

    /**
     * معالجة استدعاء الإرجاع من بوابة الدفع
     */
    public function handleCallback(Request $request, string $gateway): JsonResponse
    {
        try {
            $result = $this->paymentService->handlePaymentCallback($gateway, $request);

            if ($result['success']) {
                return $this->apiResponse(200, 'Payment completed successfully');
            }

            return $this->apiResponse(400, 'Payment verification failed');

        } catch (\Exception $e) {
            Log::error('Payment callback failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment callback processing failed', $e->getMessage());
        }
    }

    /**
     * معالجة Webhook من بوابة الدفع
     */
    public function handleWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $result = $this->paymentService->handleWebhook($gateway, $request);

            if ($result['success']) {
                return $this->apiResponse(200, 'Webhook processed successfully');
            }

            return $this->apiResponse(400, 'Webhook processing failed', $result['error'] ?? null);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Webhook processing failed', $e->getMessage());
        }
    }

    /**
     * التحقق من حالة الدفع
     */
    public function getPaymentStatus(Request $request, string $gateway, string $transactionId): JsonResponse
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
            Log::error('Payment status check failed', [
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Payment status check failed', $e->getMessage());
        }
    }

    /**
     * معالجة استرداد الأموال
     */
    public function processRefund(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'gateway' => 'required|string',
                'transaction_id' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'reason' => 'nullable|string|max:255'
            ]);

            $result = $this->paymentService->processRefund(
                $request->gateway,
                $request->transaction_id,
                $request->amount,
                $request->reason
            );

            if ($result['success']) {
                return $this->apiResponse(200, 'Refund processed successfully', null, $result);
            }

            return $this->apiResponse(400, 'Refund processing failed', $result['error'] ?? null);

        } catch (\Exception $e) {
            Log::error('Refund processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Refund processing failed', $e->getMessage());
        }
    }

    /**
     * إلغاء الدفع
     */
    public function cancelPayment(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'gateway' => 'required|string',
                'transaction_id' => 'required|string'
            ]);

            $result = $this->paymentService->cancelPayment(
                $request->gateway,
                $request->transaction_id
            );

            if ($result['success']) {
                return $this->apiResponse(200, 'Payment cancelled successfully', null, $result);
            }

            return $this->apiResponse(400, 'Payment cancellation failed', $result['error'] ?? null);

        } catch (\Exception $e) {
            Log::error('Payment cancellation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Payment cancellation failed', $e->getMessage());
        }
    }

    /**
     * حساب رسوم المعاملة
     */
    public function calculateFees(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'gateway' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency' => 'nullable|string|size:3'
            ]);

            $result = $this->paymentService->calculateTransactionFees(
                $request->gateway,
                $request->amount,
                $request->currency ?? 'USD'
            );

            return $this->apiResponse(
                200,
                'Transaction fees calculated successfully',
                null,
                $result
            );

        } catch (\Exception $e) {
            Log::error('Fee calculation failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Fee calculation failed', $e->getMessage());
        }
    }

    /**
     * اختبار اتصال بوابة الدفع
     */
    public function testGateway(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'gateway' => 'required|string'
            ]);

            $result = $this->paymentService->testGatewayConnection($request->gateway);

            return $this->apiResponse(
                $result['success'] ? 200 : 400,
                $result['message'],
                null,
                $result
            );

        } catch (\Exception $e) {
            Log::error('Gateway test failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Gateway test failed', $e->getMessage());
        }
    }

    /**
     * Get invoice details
     */
    public function getInvoice(Request $request, string $invoiceId): JsonResponse
    {
        try {
            // Fetch the actual invoice from database
            $invoice = \App\Models\Invoice::find($invoiceId);

            if (!$invoice) {
                return $this->apiResponse(404, 'Invoice not found');
            }

            // Check if user owns this invoice (through order ownership)
            if (auth()->check() && $invoice->order->user_id !== auth()->id()) {
                return $this->apiResponse(403, 'Unauthorized access to invoice');
            }

            return $this->apiResponse(
                200,
                'Invoice retrieved successfully',
                null,
                [
                    'invoice_id' => $invoice->id,
                    'order_id' => $invoice->order_id,
                    'payment_status' => $invoice->payment_status,
                    'payment_gateway' => $invoice->payment_gateway,
                    'transaction_id' => $invoice->transaction_id,
                    'payment_option' => $invoice->payment_option,
                    'tax_amount' => $invoice->tax_amount,
                    'service_charge_amount' => $invoice->service_charge_amount,
                    'final_amount' => $invoice->final_amount,
                    'payment_details' => $invoice->payment_details,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at,
                    'order' => [
                        'id' => $invoice->order->id,
                        'status' => $invoice->order->status,
                        'total_amount' => $invoice->order->total_amount,
                        'user_id' => $invoice->order->user_id
                    ]
                ]
            );

        } catch (\Exception $e) {
            Log::error('Invoice retrieval failed', [
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Invoice retrieval failed', $e->getMessage());
        }
    }

    /**
     * Get payment status for a specific order
     */
    public function getOrderPaymentStatus(Request $request, Order $order): JsonResponse
    {
        try {
            // التحقق من ملكية الطلب للمستخدم المسجل
            if ($order->user_id !== auth()->id()) {
                return $this->apiResponse(404, 'Order not found or unauthorized');
            }

            // Get the invoice for this order
            $invoice = $order->invoice;

            if (!$invoice) {
                return $this->apiResponse(404, 'No payment found for this order');
            }

            // If we have a transaction ID and gateway, get live status from gateway
            if ($invoice->transaction_id && $invoice->payment_gateway) {
                try {
                    $gatewayStatus = $this->paymentService->getPaymentStatus(
                        $invoice->payment_gateway,
                        $invoice->transaction_id
                    );

                    return $this->apiResponse(
                        200,
                        'Order payment status retrieved successfully',
                        null,
                        [
                            'order_id' => $order->id,
                            'invoice_id' => $invoice->id,
                            'payment_status' => $invoice->payment_status,
                            'payment_gateway' => $invoice->payment_gateway,
                            'transaction_id' => $invoice->transaction_id,
                            'final_amount' => $invoice->final_amount,
                            'gateway_status' => $gatewayStatus,
                            'created_at' => $invoice->created_at,
                            'updated_at' => $invoice->updated_at
                        ]
                    );
                } catch (\Exception $gatewayError) {
                    Log::warning('Gateway status check failed, returning local status', [
                        'order_id' => $order->id,
                        'invoice_id' => $invoice->id,
                        'error' => $gatewayError->getMessage()
                    ]);
                }
            }

            // Return local payment status
            return $this->apiResponse(
                200,
                'Order payment status retrieved successfully',
                null,
                [
                    'order_id' => $order->id,
                    'invoice_id' => $invoice->id,
                    'payment_status' => $invoice->payment_status,
                    'payment_gateway' => $invoice->payment_gateway,
                    'transaction_id' => $invoice->transaction_id,
                    'final_amount' => $invoice->final_amount,
                    'tax_amount' => $invoice->tax_amount,
                    'service_charge_amount' => $invoice->service_charge_amount,
                    'created_at' => $invoice->created_at,
                    'updated_at' => $invoice->updated_at
                ]
            );

        } catch (\Exception $e) {
            Log::error('Order payment status retrieval failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Failed to retrieve order payment status', $e->getMessage());
        }
    }
}
