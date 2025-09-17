<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\MenuItem;
use Laravel\Sanctum\Sanctum;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test menu items
        MenuItem::factory()->count(3)->create();

        // Create test order
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
            'total_amount' => 100.00
        ]);
    }

    /**
     * Test Get Payment Methods Endpoint
     */
    public function test_get_payment_methods()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/payment-methods');

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
                ->assertJsonCount(2, 'data');
    }

    /**
     * Test Get Payment Gateways Endpoint
     */
    public function test_get_payment_gateways()
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
                ])
                ->assertJsonCount(3, 'data');

        // Check that all three gateways are present
        $gateways = $response->json('data');
        $gatewayIds = collect($gateways)->pluck('id')->toArray();

        $this->assertContains('stripe', $gatewayIds);
        $this->assertContains('paypal', $gatewayIds);
        $this->assertContains('paymob', $gatewayIds);
    }

    /**
     * Test Payment Processing with Stripe
     */
    public function test_process_payment_with_stripe()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'stripe',
            'payment_data' => [
                'currency' => 'usd'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'invoice' => [
                            'id',
                            'final_amount',
                            'tax_amount',
                            'service_charge_amount'
                        ],
                        'payment_result' => [
                            'success',
                            'transaction_id',
                            'payment_method'
                        ]
                    ]
                ]);

        $this->assertEquals('stripe', $response->json('data.payment_result.payment_method'));
    }

    /**
     * Test Payment Processing with PayPal
     */
    public function test_process_payment_with_paypal()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 2,
            'payment_gateway' => 'paypal',
            'payment_data' => [
                'currency' => 'USD'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'next_action' => [
                            'type',
                            'url'
                        ]
                    ]
                ]);

        $this->assertEquals('redirect', $response->json('data.next_action.type'));
        $this->assertEquals('paypal', $response->json('data.payment_result.payment_method'));
    }

    /**
     * Test Payment Processing with Paymob Card
     */
    public function test_process_payment_with_paymob_card()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => [
                'payment_method' => 'card',
                'currency' => 'EGP'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
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

        $this->assertEquals('iframe', $response->json('data.next_action.type'));
        $this->assertEquals('paymob', $response->json('data.payment_result.payment_method'));
    }

    /**
     * Test Payment Processing with InstaPay
     */
    public function test_process_payment_with_instapay()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'paymob',
            'payment_data' => [
                'payment_method' => 'instapay',
                'mobile_number' => '+201234567890',
                'currency' => 'EGP'
            ]
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'instapay_data' => [
                            'mobile_number',
                            'instapay_url'
                        ]
                    ]
                ]);

        $this->assertEquals('+201234567890', $response->json('data.instapay_data.mobile_number'));
    }

    /**
     * Test Payment with Unsupported Gateway
     */
    public function test_payment_with_unsupported_gateway()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'unsupported_gateway',
            'payment_data' => []
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Unsupported payment gateway. Only PayPal, Stripe, and Paymob are supported.'
                ]);
    }

    /**
     * Test Payment for Already Paid Order
     */
    public function test_payment_for_already_paid_order()
    {
        Sanctum::actingAs($this->user);

        // Mark order as paid
        $this->order->update(['status' => 'paid']);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'stripe',
            'payment_data' => []
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(400)
                ->assertJson([
                    'message' => 'Order is already paid'
                ]);
    }

    /**
     * Test Get Payment Status
     */
    public function test_get_payment_status()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/orders/{$this->order->id}/payment-status");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'order_id',
                        'order_status',
                        'payment_status',
                        'final_amount'
                    ]
                ]);
    }

    /**
     * Test Legacy Payment Processing
     */
    public function test_legacy_payment_processing()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 2
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay-legacy", $paymentData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'final_amount',
                        'tax_amount',
                        'service_charge_amount'
                    ]
                ]);

        // Verify the order is marked as paid
        $this->order->refresh();
        $this->assertEquals('paid', $this->order->status);
    }

    /**
     * Test Unauthorized Access to Payment
     */
    public function test_unauthorized_payment_access()
    {
        // Don't authenticate user
        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'stripe'
        ];

        $response = $this->postJson("/api/v1/orders/{$this->order->id}/pay", $paymentData);

        $response->assertStatus(401);
    }

    /**
     * Test Payment for Non-existent Order
     */
    public function test_payment_for_nonexistent_order()
    {
        Sanctum::actingAs($this->user);

        $paymentData = [
            'payment_option' => 1,
            'payment_gateway' => 'stripe'
        ];

        $response = $this->postJson("/api/v1/orders/999999/pay", $paymentData);

        $response->assertStatus(404)
                ->assertJson([
                    'message' => 'Order not found or unauthorized'
                ]);
    }
}
