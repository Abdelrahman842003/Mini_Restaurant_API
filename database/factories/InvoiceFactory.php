<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $paymentGateways = ['paypal', 'stripe'];
        $paymentStatuses = ['pending', 'completed', 'failed', 'cancelled'];

        return [
            'order_id' => Order::factory(),
            'payment_option' => $this->faker->randomElement([1, 2]),
            'tax_amount' => $this->faker->randomFloat(2, 0, 50),
            'service_charge_amount' => $this->faker->randomFloat(2, 5, 30),
            'final_amount' => $this->faker->randomFloat(2, 50, 200),
            'payment_gateway' => $this->faker->randomElement($paymentGateways),
            'payment_status' => $this->faker->randomElement($paymentStatuses),
            'transaction_id' => $this->generateTransactionId(),
            'payment_details' => $this->generatePaymentDetails(),
        ];
    }

    /**
     * إنشاء فاتورة مع PayPal
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_gateway' => 'paypal',
            'transaction_id' => 'PAY-' . strtoupper($this->faker->bothify('??############')),
            'payment_details' => [
                'approval_url' => 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_express-checkout&token=EC-TEST123',
                'redirect_required' => true,
                'payment_method' => 'paypal'
            ]
        ]);
    }

    /**
     * إنشاء فاتورة مع Stripe
     */
    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_gateway' => 'stripe',
            'transaction_id' => 'pi_' . strtolower($this->faker->bothify('??############')),
            'payment_details' => [
                'client_secret' => 'pi_test_client_secret_' . $this->faker->uuid(),
                'payment_method' => 'stripe',
                'currency' => 'usd'
            ]
        ]);
    }

    /**
     * إنشاء فاتورة مكتملة الدفع
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'completed',
            'payment_details' => array_merge(
                $attributes['payment_details'] ?? [],
                [
                    'completed_at' => now()->toISOString(),
                    'status' => 'succeeded'
                ]
            )
        ]);
    }

    /**
     * إنشاء فاتورة معلقة
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'pending',
        ]);
    }

    /**
     * إنشاء فاتورة فاشلة
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'failed',
            'payment_details' => array_merge(
                $attributes['payment_details'] ?? [],
                [
                    'error_message' => 'Payment failed due to insufficient funds',
                    'failed_at' => now()->toISOString()
                ]
            )
        ]);
    }

    /**
     * إنشاء فاتورة ملغاة
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'cancelled',
            'payment_details' => array_merge(
                $attributes['payment_details'] ?? [],
                [
                    'cancelled_at' => now()->toISOString(),
                    'cancellation_reason' => 'User cancelled payment'
                ]
            )
        ]);
    }

    /**
     * توليد معرف معاملة تجريبي
     */
    private function generateTransactionId(): string
    {
        $gateways = [
            'paypal' => 'PAY-',
            'stripe' => 'pi_',
        ];

        $gateway = $this->faker->randomElement(array_keys($gateways));
        $prefix = $gateways[$gateway];

        return $prefix . strtoupper($this->faker->bothify('??############'));
    }

    /**
     * توليد تفاصيل دفع تجريبية
     */
    private function generatePaymentDetails(): array
    {
        return [
            'payment_method' => $this->faker->randomElement(['paypal', 'stripe']),
            'currency' => 'usd',
            'created_at' => now()->toISOString(),
            'metadata' => [
                'order_type' => 'restaurant',
                'customer_id' => $this->faker->uuid(),
            ]
        ];
    }
}
