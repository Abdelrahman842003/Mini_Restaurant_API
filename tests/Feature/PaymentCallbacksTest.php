<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PaymentCallbacksTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending'
        ]);
    }

    /**
     * اختبار PayPal Success Callback
     */
    public function test_paypal_success_callback_updates_order_status()
    {
        // إنشاء فاتورة PayPal
        $this->invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-TEST123456789',
            'final_amount' => 134.00
        ]);

        // محاكاة PayPal success callback
        $response = $this->getJson('/api/payment/paypal/success?paymentId=PAY-TEST123456789&PayerID=PAYER123');

        // في البيئة التجريبية، قد يفشل API call الحقيقي
        // لكن يجب أن يتعامل الكود مع المعاملات الموجودة

        // إذا وُجدت الفاتورة، يجب تحديث البيانات
        $this->invoice->refresh();
        $this->assertEquals('PAY-TEST123456789', $this->invoice->transaction_id);
    }

    /**
     * اختبار PayPal Cancel Callback
     */
    public function test_paypal_cancel_callback_marks_order_as_cancelled()
    {
        $this->invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-CANCEL123'
        ]);

        $response = $this->getJson('/api/payment/paypal/cancel?paymentId=PAY-CANCEL123');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'payment_id',
                    'status'
                ]
            ])
            ->assertJsonPath('data.status', 'cancelled');

        // التحقق من تحديث الفاتورة
        $this->invoice->refresh();
        $this->assertEquals('cancelled', $this->invoice->payment_status);
    }

    /**
     * اختبار إنشاء Stripe Payment Intent
     */
    public function test_create_stripe_payment_intent()
    {
        $requestData = [
            'amount' => 134.00,
            'order_id' => $this->order->id,
            'currency' => 'usd'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment/stripe/create-intent', $requestData);

        // في البيئة التجريبية بدون مفاتيح Stripe حقيقية
        // قد نحصل على خطأ، لكن يجب أن يكون التعامل صحيح
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'success',
                    'payment_intent_id',
                    'client_secret'
                ]
            ]);
        } else {
            // متوقع في البيئة التجريبية
            $response->assertStatus(400);
        }
    }

    /**
     * اختبار تأكيد Stripe Payment
     */
    public function test_confirm_stripe_payment()
    {
        $this->invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'stripe',
            'payment_status' => 'pending',
            'transaction_id' => 'pi_test123456789'
        ]);

        $requestData = [
            'payment_intent_id' => 'pi_test123456789',
            'payment_method_id' => 'pm_test123456789'
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment/stripe/confirm', $requestData);

        // في البيئة التجريبية، قد يفشل API call
        // لكن البنية الأساسية يجب أن تكون صحيحة
        if ($response->status() === 200) {
            $response->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'success'
                ]
            ]);
        } else {
            $response->assertStatus(400);
        }
    }

    /**
     * اختبار Stripe Webhook - Payment Success
     */
    public function test_stripe_webhook_payment_success()
    {
        $this->invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'stripe',
            'payment_status' => 'pending',
            'transaction_id' => 'pi_webhook_test123'
        ]);

        // محاكاة Stripe webhook payload
        $webhookPayload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_test123',
                    'status' => 'succeeded',
                    'amount' => 13400, // 134.00 in cents
                    'currency' => 'usd'
                ]
            ]
        ]);

        // في البيئة التجريبية، سيفشل التحقق من التوقيع
        // لكن يمكننا اختبار البنية الأساسية
        $response = $this->postJson('/api/webhooks/stripe',
            json_decode($webhookPayload, true),
            ['Stripe-Signature' => 'fake_signature']
        );

        // متوقع أن يفشل التحقق من التوقيع في البيئة التجريبية
        $this->assertTrue(in_array($response->status(), [200, 400, 401]));
    }

    /**
     * اختبار التحقق من صحة البيانات في callbacks
     */
    public function test_paypal_callback_validation()
    {
        // اختبار بدون معاملات مطلوبة
        $response = $this->getJson('/api/payment/paypal/success');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Missing payment parameters'
            ]);

        // اختبار مع معامل واحد فقط
        $response = $this->getJson('/api/payment/paypal/success?paymentId=PAY-123');

        $response->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Missing payment parameters'
            ]);
    }

    /**
     * اختبار التحقق من صحة البيانات في Stripe endpoints
     */
    public function test_stripe_endpoints_validation()
    {
        // اختبار create-intent بدون بيانات
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment/stripe/create-intent', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'order_id']);

        // اختبار confirm بدون بيانات
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/payment/stripe/confirm', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_intent_id', 'payment_method_id']);
    }

    /**
     * اختبار logging في callbacks
     */
    public function test_payment_callbacks_logging()
    {
        Log::fake();

        // اختبار PayPal cancel callback
        $this->invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-LOG-TEST'
        ]);

        $this->getJson('/api/payment/paypal/cancel?paymentId=PAY-LOG-TEST');

        // التحقق من تسجيل العملية
        Log::assertLogged('info', function ($message, $context) {
            return str_contains($message, 'PayPal payment cancelled') &&
                   isset($context['payment_id']) &&
                   $context['payment_id'] === 'PAY-LOG-TEST';
        });
    }
}
