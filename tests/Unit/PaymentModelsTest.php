<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار Invoice Model - العلاقات والوظائف
     */
    public function test_invoice_model_relationships_and_methods()
    {
        // إنشاء بيانات تجريبية
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);
        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'payment_gateway' => 'paypal',
            'payment_status' => 'pending',
            'transaction_id' => 'PAY-123456789'
        ]);

        // اختبار العلاقة مع Order
        $this->assertInstanceOf(Order::class, $invoice->order);
        $this->assertEquals($order->id, $invoice->order->id);

        // اختبار الـ accessors
        $this->assertEquals('PayPal', $invoice->payment_gateway_name);
        $this->assertEquals('Payment is being processed', $invoice->payment_status_description);

        // إصلاح اختبار masked transaction id - اختبار مرن للنمط
        $maskedId = $invoice->masked_transaction_id;
        $this->assertStringStartsWith('PAY-', $maskedId);
        $this->assertStringContainsString('****', $maskedId);
        $this->assertMatchesRegularExpression('/PAY-\*{4}\d{3,4}$/', $maskedId);

        // اختبار status methods
        $this->assertTrue($invoice->isPending());
        $this->assertFalse($invoice->isPaid());
        $this->assertFalse($invoice->isFailed());
        $this->assertFalse($invoice->isCancelled());

        // اختبار تحديث الحالة
        $invoice->update(['payment_status' => 'completed']);
        $this->assertTrue($invoice->isPaid());
        $this->assertFalse($invoice->isPending());
    }

    /**
     * اختبار Order Model - العلاقات والوظائف
     */
    public function test_order_model_relationships_and_methods()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 100.50
        ]);

        // اختبار العلاقة مع User
        $this->assertInstanceOf(User::class, $order->user);
        $this->assertEquals($user->id, $order->user->id);

        // اختبار status methods
        $this->assertTrue($order->isPending());
        $this->assertFalse($order->isPaid());
        $this->assertFalse($order->isCancelled());

        // اختبار تحديث الحالة
        $order->update(['status' => 'paid']);
        $this->assertTrue($order->isPaid());
        $this->assertFalse($order->isPending());
    }

    /**
     * اختبار Scopes في Invoice Model
     */
    public function test_invoice_model_scopes()
    {
        $user = User::factory()->create();
        $order1 = Order::factory()->create(['user_id' => $user->id]);
        $order2 = Order::factory()->create(['user_id' => $user->id]);

        // إنشاء فواتير بحالات مختلفة
        $paidInvoice = Invoice::factory()->create([
            'order_id' => $order1->id,
            'payment_status' => 'completed',
            'payment_gateway' => 'paypal'
        ]);

        $pendingInvoice = Invoice::factory()->create([
            'order_id' => $order2->id,
            'payment_status' => 'pending',
            'payment_gateway' => 'stripe'
        ]);

        // اختبار Paid scope
        $paidInvoices = Invoice::paid()->get();
        $this->assertCount(1, $paidInvoices);
        $this->assertEquals($paidInvoice->id, $paidInvoices->first()->id);

        // اختبار Pending scope
        $pendingInvoices = Invoice::pending()->get();
        $this->assertCount(1, $pendingInvoices);
        $this->assertEquals($pendingInvoice->id, $pendingInvoices->first()->id);

        // اختبار ByGateway scope
        $paypalInvoices = Invoice::byGateway('paypal')->get();
        $this->assertCount(1, $paypalInvoices);
        $this->assertEquals('paypal', $paypalInvoices->first()->payment_gateway);

        $stripeInvoices = Invoice::byGateway('stripe')->get();
        $this->assertCount(1, $stripeInvoices);
        $this->assertEquals('stripe', $stripeInvoices->first()->payment_gateway);
    }

    /**
     * اختبار Scopes في Order Model
     */
    public function test_order_model_scopes()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // إنشاء طلبات بحالات مختلفة
        $paidOrder = Order::factory()->create([
            'user_id' => $user1->id,
            'status' => 'paid'
        ]);

        $pendingOrder = Order::factory()->create([
            'user_id' => $user1->id,
            'status' => 'pending'
        ]);

        $user2Order = Order::factory()->create([
            'user_id' => $user2->id,
            'status' => 'pending'
        ]);

        // اختبار Paid scope
        $paidOrders = Order::paid()->get();
        $this->assertCount(1, $paidOrders);
        $this->assertEquals($paidOrder->id, $paidOrders->first()->id);

        // اختبار Pending scope
        $pendingOrders = Order::pending()->get();
        $this->assertCount(2, $pendingOrders);

        // اختبار ForUser scope
        $user1Orders = Order::forUser($user1->id)->get();
        $this->assertCount(2, $user1Orders);

        $user2Orders = Order::forUser($user2->id)->get();
        $this->assertCount(1, $user2Orders);
        $this->assertEquals($user2Order->id, $user2Orders->first()->id);
    }

    /**
     * اختبار Casts في Models
     */
    public function test_model_casts()
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.50
        ]);

        $invoice = Invoice::factory()->create([
            'order_id' => $order->id,
            'tax_amount' => 14.50,
            'service_charge_amount' => 20.75,
            'final_amount' => 135.25,
            'payment_details' => ['test' => 'data']
        ]);

        // اختبار decimal casting - Laravel قد يرجع float أو string
        $this->assertTrue(is_float($order->total_amount) || is_string($order->total_amount));
        $this->assertEquals(100.50, (float)$order->total_amount);

        $this->assertTrue(is_float($invoice->tax_amount) || is_string($invoice->tax_amount));
        $this->assertTrue(is_float($invoice->service_charge_amount) || is_string($invoice->service_charge_amount));
        $this->assertTrue(is_float($invoice->final_amount) || is_string($invoice->final_amount));

        // التحقق من القيم
        $this->assertEquals(14.50, (float)$invoice->tax_amount);
        $this->assertEquals(20.75, (float)$invoice->service_charge_amount);
        $this->assertEquals(135.25, (float)$invoice->final_amount);

        // اختبار JSON casting
        $this->assertIsArray($invoice->payment_details);
        $this->assertEquals(['test' => 'data'], $invoice->payment_details);

        // اختبار datetime casting
        $this->assertInstanceOf(\Carbon\Carbon::class, $order->created_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $invoice->created_at);
    }
}
