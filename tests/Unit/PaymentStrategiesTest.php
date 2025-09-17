<?php

namespace Tests\Unit;

use App\Http\Services\PaymentStrategies\FullServiceStrategy;
use App\Http\Services\PaymentStrategies\PaymentStrategyFactory;
use App\Http\Services\PaymentStrategies\ServiceOnlyStrategy;
use Tests\TestCase;

class PaymentStrategiesTest extends TestCase
{
    /**
     * اختبار استراتيجية الخدمة الكاملة - الحسابات الصحيحة
     */
    public function test_full_service_strategy_calculates_correctly()
    {
        $strategy = new FullServiceStrategy();
        $baseAmount = 100.0;

        $result = $strategy->calculate($baseAmount);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('service_charge_amount', $result);
        $this->assertArrayHasKey('final_amount', $result);

        // التحقق من الحسابات: 14% ضرائب + 20% خدمة
        $this->assertEquals(14.00, $result['tax_amount']); // 100 * 0.14
        $this->assertEquals(20.00, $result['service_charge_amount']); // 100 * 0.20
        $this->assertEquals(134.00, $result['final_amount']); // 100 + 14 + 20
    }

    /**
     * اختبار استراتيجية الخدمة فقط - الحسابات الصحيحة
     */
    public function test_service_only_strategy_calculates_correctly()
    {
        $strategy = new ServiceOnlyStrategy();
        $baseAmount = 100.0;

        $result = $strategy->calculate($baseAmount);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('tax_amount', $result);
        $this->assertArrayHasKey('service_charge_amount', $result);
        $this->assertArrayHasKey('final_amount', $result);

        // التحقق من الحسابات: 0% ضرائب + 15% خدمة
        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(15.00, $result['service_charge_amount']); // 100 * 0.15
        $this->assertEquals(115.00, $result['final_amount']); // 100 + 0 + 15
    }

    /**
     * اختبار مصنع الاستراتيجيات - إنشاء الاستراتيجية الصحيحة
     */
    public function test_payment_strategy_factory_creates_correct_strategy()
    {
        $factory = new PaymentStrategyFactory();

        // اختبار إنشاء استراتيجية الخدمة الكاملة
        $strategy1 = $factory->create(1);
        $this->assertInstanceOf(FullServiceStrategy::class, $strategy1);

        // اختبار إنشاء استراتيجية الخدمة فقط
        $strategy2 = $factory->create(2);
        $this->assertInstanceOf(ServiceOnlyStrategy::class, $strategy2);
    }

    /**
     * اختبار مصنع الاستراتيجيات - رفض خيار غير صالح
     */
    public function test_payment_strategy_factory_rejects_invalid_option()
    {
        $factory = new PaymentStrategyFactory();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payment option');

        $factory->create(999); // خيار غير موجود
    }

    /**
     * اختبار الحسابات مع مبالغ مختلفة
     */
    public function test_strategies_with_different_amounts()
    {
        $fullServiceStrategy = new FullServiceStrategy();
        $serviceOnlyStrategy = new ServiceOnlyStrategy();

        // اختبار مع مبالغ مختلفة
        $amounts = [50.0, 150.0, 99.99, 1000.0];

        foreach ($amounts as $amount) {
            // Full Service Strategy
            $fullResult = $fullServiceStrategy->calculate($amount);
            $expectedTax = round($amount * 0.14, 2);
            $expectedService = round($amount * 0.20, 2);
            $expectedTotal = round($amount + $expectedTax + $expectedService, 2);

            $this->assertEquals($expectedTax, $fullResult['tax_amount']);
            $this->assertEquals($expectedService, $fullResult['service_charge_amount']);
            $this->assertEquals($expectedTotal, $fullResult['final_amount']);

            // Service Only Strategy
            $serviceResult = $serviceOnlyStrategy->calculate($amount);
            $expectedServiceOnly = round($amount * 0.15, 2);
            $expectedTotalServiceOnly = round($amount + $expectedServiceOnly, 2);

            $this->assertEquals(0.00, $serviceResult['tax_amount']);
            $this->assertEquals($expectedServiceOnly, $serviceResult['service_charge_amount']);
            $this->assertEquals($expectedTotalServiceOnly, $serviceResult['final_amount']);
        }
    }

    /**
     * اختبار التعامل مع مبالغ صغيرة جداً
     */
    public function test_strategies_with_small_amounts()
    {
        $fullServiceStrategy = new FullServiceStrategy();
        $serviceOnlyStrategy = new ServiceOnlyStrategy();

        $smallAmount = 0.01;

        $fullResult = $fullServiceStrategy->calculate($smallAmount);
        $this->assertEquals(0.00, $fullResult['tax_amount']); // 0.01 * 0.14 = 0.0014 -> rounds to 0.00
        $this->assertEquals(0.00, $fullResult['service_charge_amount']); // 0.01 * 0.20 = 0.002 -> rounds to 0.00
        $this->assertEquals(0.01, $fullResult['final_amount']);

        $serviceResult = $serviceOnlyStrategy->calculate($smallAmount);
        $this->assertEquals(0.00, $serviceResult['tax_amount']);
        $this->assertEquals(0.00, $serviceResult['service_charge_amount']); // 0.01 * 0.15 = 0.0015 -> rounds to 0.00
        $this->assertEquals(0.01, $serviceResult['final_amount']);
    }
}
