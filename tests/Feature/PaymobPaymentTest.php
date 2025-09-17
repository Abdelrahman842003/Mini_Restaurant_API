<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Table;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Http;

class PaymobPaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'customer@test.com',
            'phone' => '+201000000000'
        ]);

        // إنشاء طلب للاختبار
        $table = Table::factory()->create(['capacity' => 4]);
        $menuItem = MenuItem::factory()->create(['price' => 100.00]);

        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'table_id' => $table->id,
            'total_amount' => 100.00,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function test_can_get_payment_gateways_including_paymob()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/payment-gateways');

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
                ]);

        $gateways = $response->json('data');
        $paymobGateway = collect($gateways)->firstWhere('id', 'paymob');

        $this->assertNotNull($paymobGateway);
        $this->assertEquals('Paymob', $paymobGateway['name']);
        $this->assertEquals('iframe', $paymobGateway['type']);
        $this->assertArrayHasKey('methods', $paymobGateway);

        // التحقق من وجود Card و InstaPay
        $methods = $paymobGateway['methods'];
        $this->assertCount(2, $methods);
        $this->assertEquals('card', $methods[0]['id']);
        $this->assertEquals('instapay', $methods[1]['id']);
    }

    /** @test */
    public function test_can_process_paymob_card_payment()
    {
        Sanctum::actingAs($this->user);

        // Mock Paymob API responses
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'fake_auth_token'
            ], 200),

            'accept.paymob.com/api/ecommerce/orders' => Http::response([
                'id' => 12345,
                'merchant_order_id' => $this->order->id
            ], 200),

            'accept.paymob.com/api/acceptance/payment_keys' => Http::response([
                'token' => 'fake_payment_key'
            ], 200)
        ]);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => [
                'payment_method' => 'card'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'invoice',
                        'payment_result',
                        'next_action' => [
                            'type',
                            'url'
                        ],
                        'paymob_data' => [
                            'payment_key',
                            'order_id'
                        ]
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals('iframe', $data['next_action']['type']);
        $this->assertStringContains('payment_token=fake_payment_key', $data['next_action']['url']);
        $this->assertEquals('fake_payment_key', $data['paymob_data']['payment_key']);
        $this->assertEquals(12345, $data['paymob_data']['order_id']);
    }

    /** @test */
    public function test_can_process_instapay_payment()
    {
        Sanctum::actingAs($this->user);

        // Mock Paymob API responses for InstaPay
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'fake_auth_token'
            ], 200),

            'accept.paymob.com/api/ecommerce/orders' => Http::response([
                'id' => 12345,
                'merchant_order_id' => $this->order->id
            ], 200),

            'accept.paymob.com/api/acceptance/payment_keys' => Http::response([
                'token' => 'fake_instapay_key'
            ], 200),

            'accept.paymob.com/api/acceptance/payments/pay' => Http::response([
                'redirect_url' => 'https://instapay.redirect.url',
                'success' => true
            ], 200)
        ]);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => [
                'payment_method' => 'instapay',
                'mobile_number' => '01000000000'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'invoice',
                        'payment_result',
                        'paymob_data',
                        'instapay_data' => [
                            'mobile_number',
                            'instapay_url'
                        ]
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals('01000000000', $data['instapay_data']['mobile_number']);
        $this->assertEquals('https://instapay.redirect.url', $data['instapay_data']['instapay_url']);
    }

    /** @test */
    public function test_instapay_requires_mobile_number()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => [
                'payment_method' => 'instapay'
                // missing mobile_number
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(400)
                ->assertJson([
                    'status' => 400,
                    'message' => 'Payment processing failed'
                ]);
    }

    /** @test */
    public function test_paymob_success_callback()
    {
        $invoice = \App\Models\Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paymob',
            'payment_status' => 'pending'
        ]);

        $response = $this->getJson("/api/payment/paymob/success?order_id={$this->order->id}&transaction_id=txn_12345");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'order_id',
                        'transaction_id',
                        'payment_status',
                        'redirect_url'
                    ]
                ]);
    }

    /** @test */
    public function test_paymob_cancel_callback()
    {
        $invoice = \App\Models\Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paymob',
            'payment_status' => 'pending'
        ]);

        $response = $this->getJson("/api/payment/paymob/cancel?order_id={$this->order->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 200,
                    'message' => 'Payment cancelled'
                ]);

        // التحقق من تحديث حالة الفاتورة
        $invoice->refresh();
        $this->assertEquals('cancelled', $invoice->payment_status);
    }

    /** @test */
    public function test_paymob_webhook_callback_successful_payment()
    {
        $invoice = \App\Models\Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paymob',
            'payment_status' => 'pending'
        ]);

        // Mock webhook data with valid HMAC
        $webhookData = [
            'id' => 'txn_123456',
            'success' => true,
            'error_occured' => false,
            'order' => [
                'merchant_order_id' => $this->order->id
            ],
            'amount_cents' => 10000,
            'created_at' => now()->toISOString(),
            'currency' => 'EGP',
            'has_parent_transaction' => false,
            'integration_id' => 123,
            'is_3d_secure' => false,
            'is_auth' => false,
            'is_capture' => true,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'owner' => 123,
            'pending' => false,
            'source_data_pan' => '1234',
            'source_data_sub_type' => 'CARD',
            'source_data_type' => 'card'
        ];

        // Generate HMAC for test
        $hmacSecret = config('services.paymob.hmac_secret', 'test_secret');
        $hmacData = implode('', [
            $webhookData['amount_cents'],
            $webhookData['created_at'],
            $webhookData['currency'],
            $webhookData['error_occured'] ? 'true' : 'false',
            $webhookData['has_parent_transaction'] ? 'true' : 'false',
            $webhookData['id'],
            $webhookData['integration_id'],
            $webhookData['is_3d_secure'] ? 'true' : 'false',
            $webhookData['is_auth'] ? 'true' : 'false',
            $webhookData['is_capture'] ? 'true' : 'false',
            $webhookData['is_refunded'] ? 'true' : 'false',
            $webhookData['is_standalone_payment'] ? 'true' : 'false',
            $webhookData['is_voided'] ? 'true' : 'false',
            $webhookData['order']['merchant_order_id'],
            $webhookData['owner'],
            $webhookData['pending'] ? 'true' : 'false',
            $webhookData['source_data_pan'],
            $webhookData['source_data_sub_type'],
            $webhookData['source_data_type'],
            $webhookData['success'] ? 'true' : 'false'
        ]);

        $webhookData['hmac'] = hash_hmac('sha512', $hmacData, $hmacSecret);

        $response = $this->postJson('/api/payment/paymob/callback', $webhookData);

        $response->assertStatus(200);

        // التحقق من تحديث الطلب والفاتورة
        $this->order->refresh();
        $invoice->refresh();

        $this->assertEquals('paid', $this->order->status);
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals('txn_123456', $invoice->transaction_id);
    }

    /** @test */
    public function test_can_check_instapay_payment_status()
    {
        Sanctum::actingAs($this->user);

        $invoice = \App\Models\Invoice::factory()->create([
            'order_id' => $this->order->id,
            'payment_gateway' => 'paymob',
            'payment_status' => 'paid',
            'transaction_id' => 'instapay_txn_123'
        ]);

        $response = $this->postJson('/api/v1/payment/paymob/instapay/status', [
            'payment_key' => 'fake_payment_key',
            'order_id' => $this->order->id
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'order_id',
                        'payment_status',
                        'transaction_id',
                        'payment_method'
                    ]
                ]);

        $data = $response->json('data');
        $this->assertEquals($this->order->id, $data['order_id']);
        $this->assertEquals('paid', $data['payment_status']);
        $this->assertEquals('instapay_txn_123', $data['transaction_id']);
        $this->assertEquals('instapay', $data['payment_method']);
    }

    /** @test */
    public function test_unauthorized_user_cannot_check_other_user_instapay_status()
    {
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->postJson('/api/v1/payment/paymob/instapay/status', [
            'payment_key' => 'fake_payment_key',
            'order_id' => $this->order->id
        ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function test_complete_paymob_payment_workflow()
    {
        Sanctum::actingAs($this->user);

        // 1. الحصول على بوابات الدفع
        $gatewaysResponse = $this->getJson('/api/v1/payment-gateways');
        $gatewaysResponse->assertStatus(200);

        $gateways = $gatewaysResponse->json('data');
        $paymobGateway = collect($gateways)->firstWhere('id', 'paymob');
        $this->assertNotNull($paymobGateway);

        // 2. Mock Paymob APIs
        Http::fake([
            'accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'fake_auth_token'], 200),
            'accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 12345], 200),
            'accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'fake_payment_key'], 200)
        ]);

        // 3. بدء عملية الدفع
        $paymentResponse = $this->postJson("/api/v1/orders/{$this->order->id}/pay", [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => ['payment_method' => 'card']
        ]);

        $paymentResponse->assertStatus(201);
        $paymentData = $paymentResponse->json('data');

        // 4. التحقق من إنشاء الفاتورة
        $this->assertDatabaseHas('invoices', [
            'order_id' => $this->order->id,
            'payment_gateway' => 'paymob',
            'payment_status' => 'pending'
        ]);

        // 5. محاكاة webhook نجح
        $invoice = \App\Models\Invoice::where('order_id', $this->order->id)->first();

        $webhookData = [
            'id' => 'txn_success_123',
            'success' => true,
            'error_occured' => false,
            'order' => ['merchant_order_id' => $this->order->id],
            'amount_cents' => 10000,
            'created_at' => now()->toISOString(),
            'currency' => 'EGP',
            'has_parent_transaction' => false,
            'integration_id' => 123,
            'is_3d_secure' => false,
            'is_auth' => false,
            'is_capture' => true,
            'is_refunded' => false,
            'is_standalone_payment' => true,
            'is_voided' => false,
            'owner' => 123,
            'pending' => false,
            'source_data_pan' => '1234',
            'source_data_sub_type' => 'CARD',
            'source_data_type' => 'card'
        ];

        $hmacString = implode('', array_values($webhookData));
        $webhookData['hmac'] = hash_hmac('sha512', $hmacString, config('services.paymob.hmac_secret', 'test_secret'));

        $webhookResponse = $this->postJson('/api/webhooks/paymob', $webhookData);
        $webhookResponse->assertStatus(200);

        // 6. التحقق من إتمام الدفع
        $this->order->refresh();
        $invoice->refresh();

        $this->assertEquals('paid', $this->order->status);
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals('txn_success_123', $invoice->transaction_id);
    }
}
