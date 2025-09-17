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
     *
     * GET /api/v1/payment-methods
     *
     * @return JsonResponse قائمة بخيارات الدفع مع التفاصيل
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
     * عرض بوابات الدفع المتاحة - PayPal فقط
     *
     * GET /api/v1/payment-gateways
     *
     * @return JsonResponse قائمة ببوابات الدفع المدعومة
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
     *
     * POST /api/v1/orders/{order}/pay
     * Body: {
     *   "payment_option": 1,
     *   "payment_gateway": "paypal",
     *   "payment_data": {}
     * }
     *
     * @param ProcessPaymentRequest $request طلب معالجة الدفع
     * @param Order $order كائن الطلب مع Route Model Binding
     * @return JsonResponse نتيجة العملية مع تفاصيل الفاتورة
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

            // التحقق من بوابة الدفع المدعومة
            $gateway = $validated['payment_gateway'];
            if ($gateway !== 'paypal') {
                return $this->apiResponse(400, 'Unsupported payment gateway. Only PayPal is supported.');
            }

            // معالجة الدفع مع PayPal - فقط إنشاء نية الدفع
            $result = $this->paymentService->processPaymentWithGateway(
                $order,
                $validated['payment_option'],
                $gateway,
                $validated['payment_data'] ?? []
            );

            $responseData = [
                'invoice' => new InvoiceResource($result['invoice']),
                'payment_result' => $result['payment_result']
            ];

            // إذا كان الدفع يتطلب إعادة توجيه لـ PayPal
            if ($result['payment_result']['redirect_required'] ?? false) {
                $responseData['next_action'] = [
                    'type' => 'redirect',
                    'url' => $result['payment_result']['approval_url'] ?? null
                ];
            }

            return $this->apiResponse(
                201,
                'Payment intent created successfully. Please complete payment at PayPal.',
                null,
                $responseData
            );
        } catch (\Exception $e) {
            Log::error('Payment processing failed', [
                'order_id' => $order->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(400, 'Payment processing failed', $e->getMessage());
        }
    }

    /**
     * الحصول على تفاصيل فاتورة
     *
     * GET /api/v1/invoices/{invoiceId}
     */
    public function getInvoice($invoiceId): JsonResponse
    {
        try {
            $invoice = \App\Models\Invoice::with(['order.user'])
                ->where('id', $invoiceId)
                ->first();

            if (!$invoice) {
                return $this->apiResponse(404, 'Invoice not found');
            }

            // التحقق من ملكية الفاتورة
            if ($invoice->order->user_id !== auth()->id()) {
                return $this->apiResponse(403, 'Unauthorized access to invoice');
            }

            return $this->apiResponse(
                200,
                'Invoice retrieved successfully',
                null,
                new InvoiceResource($invoice)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve invoice', $e->getMessage());
        }
    }

    /**
     * الحصول على حالة الدفع
     *
     * GET /api/v1/orders/{orderId}/payment-status
     */
    public function getPaymentStatus($orderId): JsonResponse
    {
        try {
            $order = $this->orderService->getOrderForUser($orderId, auth()->id());

            if (!$order) {
                return $this->apiResponse(404, 'Order not found or unauthorized');
            }

            $invoice = $order->invoice;

            $paymentStatus = [
                'order_id' => $order->id,
                'order_status' => $order->status,
                'payment_status' => $invoice->payment_status ?? 'unpaid',
                'payment_gateway' => $invoice->payment_gateway ?? null,
                'transaction_id' => $invoice->transaction_id ?? null,
                'final_amount' => $invoice->final_amount ?? $order->total_amount
            ];

            return $this->apiResponse(
                200,
                'Payment status retrieved successfully',
                null,
                $paymentStatus
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve payment status', $e->getMessage());
        }
    }
}
