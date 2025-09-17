<?php

namespace Tests\Unit;

use App\Http\Interfaces\InvoiceRepositoryInterface;
use App\Http\Interfaces\OrderRepositoryInterface;
use App\Http\Services\PaymentService;
use App\Http\Services\PaymentStrategies\PaymentStrategyFactory;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $paymentService;
    private $orderRepository;
    private $invoiceRepository;
    private $strategyFactory;

    protected function setUp(): void
    {
        parent::setUp();

        // إنشاء Mocks للمستودعات
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->invoiceRepository = Mockery::mock(InvoiceRepositoryInterface::class);
        $this->strategyFactory = new PaymentStrategyFactory();

        $this->paymentService = new PaymentService(
            $this->orderRepository,
            $this->invoiceRepository,
            $this->strategyFactory
        );
    }

    /**
     * اختبار معالجة الدفع الناجح - الخيار الأول
     */
    public function test_process_payment_full_service_success()
    {
        // إعداد البيانات التجريبية
        $user = User::factory()->make(['id' => 1]);
        $order = Order::factory()->make([
            'id' => 1,
            'user_id' => 1,
            'total_amount' => 100.0,
            'status' => 'pending'
        ]);

        $expectedInvoice = Invoice::factory()->make([
            'id' => 1,
            'order_id' => 1,
            'payment_option' => 1,
            'tax_amount' => 14.0,
            'service_charge_amount' => 20.0,
            'final_amount' => 134.0
        ]);

        // إعداد Mock expectations
        $this->invoiceRepository->shouldReceive('create')
            ->once()
            ->with([
                'order_id' => 1,
                'payment_option' => 1,
                'tax_amount' => 14.0,
                'service_charge_amount' => 20.0,
                'final_amount' => 134.0
            ])
            ->andReturn($expectedInvoice);

        $this->orderRepository->shouldReceive('updateStatus')
            ->once()
            ->with(1, 'paid')
            ->andReturn(true);

        // تنفيذ الاختبار
        $result = $this->paymentService->processPayment($order, 1);

        // التحقق من النتائج
        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals(1, $result->order_id);
        $this->assertEquals(134.0, $result->final_amount);
    }

    /**
     * اختبار معالجة الدفع الناجح - الخيار الثاني
     */
    public function test_process_payment_service_only_success()
    {
        $order = Order::factory()->make([
            'id' => 2,
            'total_amount' => 100.0,
            'status' => 'pending'
        ]);

        $expectedInvoice = Invoice::factory()->make([
            'id' => 2,
            'order_id' => 2,
            'payment_option' => 2,
            'tax_amount' => 0.0,
            'service_charge_amount' => 15.0,
            'final_amount' => 115.0
        ]);

        $this->invoiceRepository->shouldReceive('create')
            ->once()
            ->with([
                'order_id' => 2,
                'payment_option' => 2,
                'tax_amount' => 0.0,
                'service_charge_amount' => 15.0,
                'final_amount' => 115.0
            ])
            ->andReturn($expectedInvoice);

        $this->orderRepository->shouldReceive('updateStatus')
            ->once()
            ->with(2, 'paid')
            ->andReturn(true);

        $result = $this->paymentService->processPayment($order, 2);

        $this->assertInstanceOf(Invoice::class, $result);
        $this->assertEquals(115.0, $result->final_amount);
        $this->assertEquals(0.0, $result->tax_amount);
    }

    /**
     * اختبار رفض الدفع للطلب المدفوع مسبقاً
     */
    public function test_process_payment_rejects_already_paid_order()
    {
        $order = Order::factory()->make([
            'id' => 3,
            'status' => 'paid',
            'total_amount' => 100.0
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Order is already paid.');

        $this->paymentService->processPayment($order, 1);
    }

    /**
     * اختبار الحصول على طرق الدفع المتاحة
     */
    public function test_get_payment_methods_returns_correct_structure()
    {
        $methods = $this->paymentService->getPaymentMethods();

        $this->assertIsArray($methods);
        $this->assertCount(2, $methods);

        // التحقق من الخيار الأول
        $this->assertEquals(1, $methods[0]['id']);
        $this->assertEquals('Full Service Package', $methods[0]['name']);
        $this->assertEquals(0.14, $methods[0]['tax_rate']);
        $this->assertEquals(0.20, $methods[0]['service_charge_rate']);

        // التحقق من الخيار الثاني
        $this->assertEquals(2, $methods[1]['id']);
        $this->assertEquals('Service Only', $methods[1]['name']);
        $this->assertEquals(0, $methods[1]['tax_rate']);
        $this->assertEquals(0.15, $methods[1]['service_charge_rate']);
    }

    /**
     * اختبار الحصول على بوابات الدفع المتاحة (PayPal و Stripe فقط)
     */
    public function test_get_available_gateways_returns_only_paypal_and_stripe()
    {
        $gateways = $this->paymentService->getAvailableGateways();

        $this->assertIsArray($gateways);
        $this->assertCount(2, $gateways);

        // التحقق من PayPal
        $this->assertEquals('paypal', $gateways[0]['id']);
        $this->assertEquals('PayPal', $gateways[0]['name']);
        $this->assertEquals('redirect', $gateways[0]['type']);

        // التحقق من Stripe
        $this->assertEquals('stripe', $gateways[1]['id']);
        $this->assertEquals('Stripe', $gateways[1]['name']);
        $this->assertEquals('inline', $gateways[1]['type']);
    }

    /**
     * اختبار معالجة الدفع مع بوابة PayPal - إصلاح نهائي
     */
    public function test_process_payment_with_paypal_gateway()
    {
        $order = Order::factory()->make([
            'id' => 4,
            'user_id' => 1,
            'total_amount' => 100.0,
            'status' => 'pending'
        ]);

        // إنشاء Mock مبسط للفاتورة
        $invoiceMock = Mockery::mock(Invoice::class)->makePartial();
        $invoiceMock->shouldReceive('update')->once()->andReturn(true);

        // إعداد Repository expectations
        $this->invoiceRepository->shouldReceive('create')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn($invoiceMock);

        // تنفيذ الاختبار
        $result = $this->paymentService->processPaymentWithGateway($order, 1, 'paypal', []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('invoice', $result);
        $this->assertArrayHasKey('payment_result', $result);
    }

    /**
     * اختبار رفض بوابة دفع غير مدعومة - إصلاح نهائي
     */
    public function test_process_payment_with_gateway_rejects_unsupported_gateway()
    {
        $order = Order::factory()->make([
            'id' => 5,
            'status' => 'pending'
        ]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported payment gateway. Only PayPal and Stripe are supported.');

        // استدعاء مباشر بدون database transaction
        $this->paymentService->processPaymentWithGateway($order, 1, 'unsupported_gateway', []);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
