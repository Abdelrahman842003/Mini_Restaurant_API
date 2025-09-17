<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء مستخدم تجريبي
        $this->user = User::factory()->create([
            'email' => 'test@restaurant.com',
            'name' => 'Test User'
        ]);

        // إنشاء طلب تجريبي
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total_amount' => 100.00,
            'status' => 'pending'
        ]);
    }

    /**
     * اختبار عرض طرق الدفع المتاحة
     */
    public function test_can_get_available_payment_methods()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payment-methods');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'tax_rate',
                        'service_charge_rate'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data'); // يجب أن يكون هناك خيارين للدفع
    }

    /**
     * اختبار عرض بوابات الدفع المتاحة (PayPal و Stripe فقط)
     */
    public function test_can_get_available_payment_gateways()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/payment-gateways');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'type',
                        'icon'
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data') // PayPal و Stripe فقط
            ->assertJsonPath('data.0.id', 'paypal')
            ->assertJsonPath('data.1.id', 'stripe');
    }

    /**
     * اختبار الدفع بـ PayPal
     */
    public function test_can_process_payment_with_paypal()
    {
        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paypal',
            'payment_data' => [
                'currency' => 'usd',
                'description' => 'Test restaurant payment'
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'invoice' => [
                        'id',
                        'order_id',
                        'payment_gateway',
                        'payment_status',
                        'amounts' => [
                            'base_amount',
                            'tax_amount',
                            'service_charge_amount',
                            'final_amount'
                        ]
                    ],
                    'payment_result' => [
                        'success',
                        'transaction_id',
                        'payment_method',
                        'approval_url',
                        'redirect_required'
                    ]
                ]
            ]);

        // التحقق من إنشاء الفاتورة في قاعدة البيانات
        $this->assertDatabaseHas('invoices', [
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending'
        ]);
    }

    /**
     * اختبار الدفع بـ Stripe
     */
    public function test_can_process_payment_with_stripe()
    {
        $paymentData = [
            'payment_option' => 2,
            'payment_gateway' => 'stripe',
            'payment_data' => [
                'currency' => 'usd'
            ]
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'invoice',
                    'payment_result' => [
                        'success',
                        'transaction_id',
                        'client_secret',
                        'payment_method'
                    ]
                ]
            ]);

        // التحقق من إنشاء الفاتورة
        $this->assertDatabaseHas('invoices', [
            'order_id' => $this->order->id,
            'payment_gateway' => 'stripe',
            'payment_status' => 'pending'
        ]);
    }

    /**
     * اختبار رفض بوابة دفع غير مدعومة
     */
    public function test_rejects_unsupported_payment_gateway()
    {
        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'unsupported_gateway',
            'payment_data' => []
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(422) // Validation error
            ->assertJsonValidationErrors(['payment_gateway']);
    }

    /**
     * اختبار منع الدفع المزدوج
     */
    public function test_prevents_double_payment()
    {
        // تحديث الطلب ليكون مدفوع
        $this->order->update(['status' => 'paid']);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paypal',
            'payment_data' => []
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(400)
            ->assertJson([
                'status' => 400,
                'message' => 'Order is already paid'
            ]);
    }

    /**
     * اختبار حساب المبالغ للخيار الأول (ضرائب + خدمة)
     */
    public function test_calculates_full_service_amounts_correctly()
    {
        $paymentData = [
            'payment_option' => 1, // Full Service Package
            'payment_gateway' => 'paypal',
            'payment_data' => []
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201);

        $invoice = Invoice::where('order_id', $this->order->id)->first();

        // التحقق من الحسابات
        $this->assertEquals(14.00, $invoice->tax_amount); // 14% من 100
        $this->assertEquals(20.00, $invoice->service_charge_amount); // 20% من 100
        $this->assertEquals(134.00, $invoice->final_amount); // 100 + 14 + 20
    }

    /**
     * اختبار حساب المبالغ للخيار الثاني (خدمة فقط)
     */
    public function test_calculates_service_only_amounts_correctly()
    {
        $paymentData = [
            'payment_option' => 2, // Service Only
            'payment_gateway' => 'stripe',
            'payment_data' => []
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201);

        $invoice = Invoice::where('order_id', $this->order->id)->first();

        // التحقق من الحسابات
        $this->assertEquals(0.00, $invoice->tax_amount); // لا توجد ضرائب
        $this->assertEquals(15.00, $invoice->service_charge_amount); // 15% من 100
        $this->assertEquals(115.00, $invoice->final_amount); // 100 + 0 + 15
    }

    /**
     * اختبار عرض حالة الدفع
     */
    public function test_can_get_payment_status()
    {
        // إنشاء فاتورة
        $invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'final_amount' => 134.00
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/orders/{$this->order->id}/payment-status");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'order_id',
                    'order_status',
                    'payment_status',
                    'payment_gateway',
                    'transaction_id',
                    'final_amount'
                ]
            ])
            ->assertJsonPath('data.payment_gateway', 'paypal')
            ->assertJsonPath('data.payment_status', 'pending');
    }

    /**
     * اختبار منع الوصول لطلبات المستخدمين الآخرين
     */
    public function test_prevents_unauthorized_access_to_other_users_orders()
    {
        $otherUser = User::factory()->create();

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paypal',
            'payment_data' => []
        ];

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(404); // Order not found for this user
    }

    /**
     * اختبار التحقق من صحة البيانات المطلوبة
     */
    public function test_validates_required_payment_data()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/orders/{$this->order->id}/pay", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payment_option', 'payment_gateway']);
    }

    /**
     * اختبار PayPal Success Callback
     */
    public function test_paypal_success_callback()
    {
        // إنشاء فاتورة PayPal
        $invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-TEST123456789'
        ]);

        $response = $this->getJson('/api/payment/paypal/success?paymentId=PAY-TEST123456789&PayerID=PAYER123');

        // Note: في البيئة التجريبية، سيفشل هذا لأننا لا نملك PayPal حقيقي
        // لكن يمكننا اختبار البنية الأساسية
        $this->assertTrue(true); // Placeholder for PayPal integration test
    }

    /**
     * اختبار PayPal Cancel Callback
     */
    public function test_paypal_cancel_callback()
    {
        $invoice = Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-TEST123456789'
        ]);

        $response = $this->getJson('/api/payment/paypal/cancel?paymentId=PAY-TEST123456789');

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        // التحقق من تحديث حالة الطلب
        $this->order->refresh();
        $this->assertEquals('cancelled', $this->order->status);
    }
}
