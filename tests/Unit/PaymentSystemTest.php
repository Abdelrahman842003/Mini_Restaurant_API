<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Factories\PaymentGatewayFactory;
use App\Http\Services\PaymentStrategies\PaymentStrategyFactory;
use App\Http\Services\PaymentStrategies\FullServiceStrategy;
use App\Http\Services\PaymentStrategies\ServiceOnlyStrategy;
use App\Http\Services\PaymentGateways\StripeGateway;
use App\Http\Services\PaymentGateways\PayPalGateway;
use App\Http\Services\PaymentGateways\PaymobGateway;

class PaymentSystemTest extends TestCase
{
    /**
     * Test Factory Pattern for Payment Gateways
     */
    public function test_payment_gateway_factory_creates_correct_instances()
    {
        // Test Stripe Gateway
        $stripeGateway = PaymentGatewayFactory::make('stripe');
        $this->assertInstanceOf(StripeGateway::class, $stripeGateway);
        $this->assertEquals('stripe', $stripeGateway->getGatewayName());

        // Test PayPal Gateway
        $paypalGateway = PaymentGatewayFactory::make('paypal');
        $this->assertInstanceOf(PayPalGateway::class, $paypalGateway);
        $this->assertEquals('paypal', $paypalGateway->getGatewayName());

        // Test Paymob Gateway
        $paymobGateway = PaymentGatewayFactory::make('paymob');
        $this->assertInstanceOf(PaymobGateway::class, $paymobGateway);
        $this->assertEquals('paymob', $paymobGateway->getGatewayName());
    }

    /**
     * Test Factory throws exception for unsupported gateway
     */
    public function test_payment_gateway_factory_throws_exception_for_unsupported_gateway()
    {
        $this->expectException(\InvalidArgumentException::class);
        PaymentGatewayFactory::make('unsupported_gateway');
    }

    /**
     * Test Strategy Pattern for Payment Calculations
     */
    public function test_payment_strategy_factory_creates_correct_strategies()
    {
        $strategyFactory = new PaymentStrategyFactory();

        // Test Full Service Strategy (Option 1)
        $fullServiceStrategy = $strategyFactory->create(1);
        $this->assertInstanceOf(FullServiceStrategy::class, $fullServiceStrategy);

        // Test Service Only Strategy (Option 2)
        $serviceOnlyStrategy = $strategyFactory->create(2);
        $this->assertInstanceOf(ServiceOnlyStrategy::class, $serviceOnlyStrategy);
    }

    /**
     * Test Full Service Strategy Calculations (14% tax + 20% service)
     */
    public function test_full_service_strategy_calculations()
    {
        $strategy = new FullServiceStrategy();
        $result = $strategy->calculate(100.00);

        $this->assertEquals(14.00, $result['tax_amount']);
        $this->assertEquals(20.00, $result['service_charge_amount']);
        $this->assertEquals(134.00, $result['final_amount']);
    }

    /**
     * Test Service Only Strategy Calculations (15% service only)
     */
    public function test_service_only_strategy_calculations()
    {
        $strategy = new ServiceOnlyStrategy();
        $result = $strategy->calculate(100.00);

        $this->assertEquals(0.00, $result['tax_amount']);
        $this->assertEquals(15.00, $result['service_charge_amount']);
        $this->assertEquals(115.00, $result['final_amount']);
    }

    /**
     * Test Supported Gateways List
     */
    public function test_supported_gateways_list()
    {
        $supportedGateways = PaymentGatewayFactory::getSupportedGateways();

        $this->assertCount(3, $supportedGateways);
        $this->assertContains('stripe', $supportedGateways);
        $this->assertContains('paypal', $supportedGateways);
        $this->assertContains('paymob', $supportedGateways);
    }

    /**
     * Test Gateway Support Check
     */
    public function test_gateway_support_check()
    {
        $this->assertTrue(PaymentGatewayFactory::isSupported('stripe'));
        $this->assertTrue(PaymentGatewayFactory::isSupported('paypal'));
        $this->assertTrue(PaymentGatewayFactory::isSupported('paymob'));
        $this->assertFalse(PaymentGatewayFactory::isSupported('unsupported'));
    }

    /**
     * Test Payment Data Validation for each Gateway
     */
    public function test_payment_data_validation()
    {
        $stripeGateway = PaymentGatewayFactory::make('stripe');
        $paypalGateway = PaymentGatewayFactory::make('paypal');
        $paymobGateway = PaymentGatewayFactory::make('paymob');

        // Valid payment data
        $validData = ['amount' => 100.00];

        $this->assertTrue($stripeGateway->validatePaymentData($validData));
        $this->assertTrue($paypalGateway->validatePaymentData($validData));
        $this->assertTrue($paymobGateway->validatePaymentData($validData));

        // Invalid payment data (negative amount)
        $invalidData = ['amount' => -50.00];

        $this->expectException(\Exception::class);
        $stripeGateway->validatePaymentData($invalidData);
    }

    /**
     * Test InstaPay Validation
     */
    public function test_instapay_validation()
    {
        $paymobGateway = PaymentGatewayFactory::make('paymob');

        // Valid InstaPay data
        $validInstapayData = [
            'amount' => 100.00,
            'payment_method' => 'instapay',
            'mobile_number' => '+201234567890'
        ];

        $this->assertTrue($paymobGateway->validatePaymentData($validInstapayData));

        // Invalid InstaPay data (missing mobile number)
        $invalidInstapayData = [
            'amount' => 100.00,
            'payment_method' => 'instapay'
        ];

        $this->expectException(\Exception::class);
        $paymobGateway->validatePaymentData($invalidInstapayData);
    }
}
