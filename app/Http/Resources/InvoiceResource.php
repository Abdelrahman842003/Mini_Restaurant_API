<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_option' => $this->payment_option,
            'payment_option_name' => $this->payment_option === 1 ? 'Full Service Package' : 'Service Only',

            // تفاصيل المبالغ
            'amounts' => [
                'base_amount' => $this->order->total_amount ?? 0,
                'tax_amount' => $this->tax_amount,
                'service_charge_amount' => $this->service_charge_amount,
                'final_amount' => $this->final_amount,
            ],

            // تفاصيل بوابة الدفع - PayPal أو Stripe فقط
            'payment_gateway' => $this->payment_gateway,
            'payment_gateway_name' => $this->payment_gateway_name ?? 'N/A',
            'payment_status' => $this->payment_status,
            'payment_status_description' => $this->payment_status_description ?? 'Unknown',

            // معرف المعاملة (مقنع للأمان)
            'transaction_id' => $this->masked_transaction_id ?? 'N/A',
            'raw_transaction_id' => $this->when(
                $request->user()?->id === $this->order->user_id,
                $this->transaction_id
            ),

            // تفاصيل إضافية
            'payment_details' => $this->when(
                $request->user()?->id === $this->order->user_id && $this->payment_details,
                $this->payment_details
            ),

            // معلومات الطلب
            'order' => $this->whenLoaded('order', function () {
                return [
                    'id' => $this->order->id,
                    'status' => $this->order->status,
                    'total_amount' => $this->order->total_amount,
                    'created_at' => $this->order->created_at,
                ];
            }),

            // التواريخ
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // حالات منطقية مفيدة
            'is_paid' => $this->isPaid(),
            'is_pending' => $this->isPending(),
            'is_failed' => $this->isFailed(),
            'is_cancelled' => $this->isCancelled(),

            // معلومات إضافية حسب بوابة الدفع
            'gateway_info' => $this->when($this->payment_gateway, function () {
                return match($this->payment_gateway) {
                    'paypal' => [
                        'name' => 'PayPal',
                        'type' => 'redirect',
                        'description' => 'Secure payment via PayPal',
                        'redirect_required' => true
                    ],
                    'stripe' => [
                        'name' => 'Stripe',
                        'type' => 'inline',
                        'description' => 'Credit/Debit card payment via Stripe',
                        'redirect_required' => false
                    ],
                    default => null
                };
            }),
        ];
    }
}
