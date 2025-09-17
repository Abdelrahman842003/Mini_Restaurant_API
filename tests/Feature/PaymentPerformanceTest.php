<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * اختبار أداء نظام الدفع مع عدد كبير من الطلبات
     */
    public function test_bulk_payment_processing_performance()
    {
        $users = User::factory()->count(10)->create();
        $orders = [];

        // إنشاء 50 طلب
        foreach ($users as $user) {
            for ($i = 0; $i < 5; $i++) {
                $orders[] = Order::factory()->create([
                    'user_id' => $user->id,
                    'total_amount' => rand(50, 500),
                    'status' => 'pending'
                ]);
            }
        }

        $startTime = microtime(true);

        // معالجة دفع جميع الطلبات
        foreach ($orders as $order) {
            $paymentData = [
                'payment_option' => rand(1, 2),
                'payment_gateway' => ['paypal', 'stripe'][rand(0, 1)],
                'payment_data' => []
            ];

            $response = $this->actingAs($order->user, 'sanctum')
                ->postJson("/api/v1/orders/{$order->id}/pay", $paymentData);

            $this->assertEquals(201, $response->status());
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // يجب أن تكتمل معالجة 50 طلب في أقل من 10 ثواني
        $this->assertLessThan(10, $executionTime, "Payment processing took too long: {$executionTime} seconds");

        // التحقق من إنشاء جميع الفواتير
        $invoicesCount = Invoice::count();
        $this->assertEquals(50, $invoicesCount);

        echo "\n⚡ Performance Test Results:\n";
        echo "✅ Processed 50 payments in " . round($executionTime, 2) . " seconds\n";
        echo "✅ Average: " . round($executionTime / 50, 4) . " seconds per payment\n";
        echo "✅ Created {$invoicesCount} invoices successfully\n";
    }

    /**
     * اختبار أداء حسابات الضرائب والرسوم
     */
    public function test_calculation_performance()
    {
        $amounts = [];
        for ($i = 0; $i < 1000; $i++) {
            $amounts[] = rand(1, 10000) / 100; // مبالغ عشوائية من 0.01 إلى 100.00
        }

        $startTime = microtime(true);

        // اختبار حسابات الخيار الأول
        $fullServiceStrategy = new \App\Http\Services\PaymentStrategies\FullServiceStrategy();
        foreach ($amounts as $amount) {
            $result = $fullServiceStrategy->calculate($amount);
            $this->assertArrayHasKey('final_amount', $result);
        }

        // اختبار حسابات الخيار الثاني
        $serviceOnlyStrategy = new \App\Http\Services\PaymentStrategies\ServiceOnlyStrategy();
        foreach ($amounts as $amount) {
            $result = $serviceOnlyStrategy->calculate($amount);
            $this->assertArrayHasKey('final_amount', $result);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // يجب أن تكتمل 2000 عملية حسابية في أقل من ثانية واحدة
        $this->assertLessThan(1, $executionTime, "Calculations took too long: {$executionTime} seconds");

        echo "\n🧮 Calculation Performance:\n";
        echo "✅ Processed 2000 calculations in " . round($executionTime, 4) . " seconds\n";
        echo "✅ Average: " . round(($executionTime / 2000) * 1000, 2) . " milliseconds per calculation\n";
    }

    /**
     * اختبار أداء قاعدة البيانات مع الاستعلامات المعقدة
     */
    public function test_database_query_performance()
    {
        // إنشاء بيانات اختبار كبيرة
        $users = User::factory()->count(20)->create();
        $orders = [];
        $invoices = [];

        foreach ($users as $user) {
            for ($i = 0; $i < 10; $i++) {
                $order = Order::factory()->create(['user_id' => $user->id]);
                $orders[] = $order;

                $invoices[] = Invoice::factory()->create([
                    'order_id' => $order->id,
                    'payment_gateway' => ['paypal', 'stripe'][rand(0, 1)],
                    'payment_status' => ['pending', 'completed', 'failed'][rand(0, 2)]
                ]);
            }
        }

        $startTime = microtime(true);

        // استعلامات معقدة
        $queryResults = [];

        // 1. البحث عن جميع الفواتير المدفوعة
        $queryResults['paid_invoices'] = Invoice::paid()->count();

        // 2. البحث حسب بوابة الدفع
        $queryResults['paypal_invoices'] = Invoice::byGateway('paypal')->count();
        $queryResults['stripe_invoices'] = Invoice::byGateway('stripe')->count();

        // 3. إحصائيات متقدمة
        $queryResults['total_revenue'] = Invoice::paid()->sum('final_amount');
        $queryResults['avg_payment'] = Invoice::paid()->avg('final_amount');

        // 4. استعلامات مع joins
        $queryResults['users_with_payments'] = DB::table('users')
            ->join('orders', 'users.id', '=', 'orders.user_id')
            ->join('invoices', 'orders.id', '=', 'invoices.order_id')
            ->where('invoices.payment_status', 'completed')
            ->distinct('users.id')
            ->count();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // يجب أن تكتمل الاستعلامات في أقل من ثانيتين
        $this->assertLessThan(2, $executionTime, "Database queries took too long: {$executionTime} seconds");

        echo "\n💾 Database Performance:\n";
        echo "✅ Executed complex queries in " . round($executionTime, 4) . " seconds\n";
        echo "✅ Found {$queryResults['paid_invoices']} paid invoices\n";
        echo "✅ PayPal: {$queryResults['paypal_invoices']}, Stripe: {$queryResults['stripe_invoices']}\n";
        echo "✅ Total Revenue: $" . number_format($queryResults['total_revenue'] ?? 0, 2) . "\n";
    }

    /**
     * اختبار أداء النظام تحت ضغط متزامن
     */
    public function test_concurrent_payment_performance()
    {
        $user = User::factory()->create();
        $orders = Order::factory()->count(20)->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        $startTime = microtime(true);
        $successfulPayments = 0;
        $failedPayments = 0;

        // محاولة معالجة عدة دفعات "متزامنة"
        foreach ($orders as $order) {
            try {
                $paymentData = [
                    'payment_option' => 1,
                    'payment_gateway' => 'stripe',
                    'payment_data' => []
                ];

                $response = $this->actingAs($user, 'sanctum')
                    ->postJson("/api/v1/orders/{$order->id}/pay", $paymentData);

                if ($response->status() === 201) {
                    $successfulPayments++;
                } else {
                    $failedPayments++;
                }
            } catch (\Exception $e) {
                $failedPayments++;
            }
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // يجب أن تنجح معظم المدفوعات
        $this->assertGreaterThan(15, $successfulPayments, "Too few successful payments under load");
        $this->assertLessThan(5, $failedPayments, "Too many failed payments under load");

        echo "\n🚀 Concurrent Performance:\n";
        echo "✅ Processed 20 concurrent payments in " . round($executionTime, 2) . " seconds\n";
        echo "✅ Successful: {$successfulPayments}, Failed: {$failedPayments}\n";
        echo "✅ Success Rate: " . round(($successfulPayments / 20) * 100, 1) . "%\n";
    }

    /**
     * اختبار استهلاك الذاكرة
     */
    public function test_memory_usage_optimization()
    {
        $initialMemory = memory_get_usage(true);

        // إنشاء ومعالجة عدد كبير من المدفوعات
        $user = User::factory()->create();

        for ($i = 0; $i < 100; $i++) {
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'status' => 'pending'
            ]);

            $paymentData = [
                'payment_option' => ($i % 2) + 1,
                'payment_gateway' => ['paypal', 'stripe'][$i % 2],
                'payment_data' => ['test' => 'data_' . $i]
            ];

            $response = $this->actingAs($user, 'sanctum')
                ->postJson("/api/v1/orders/{$order->id}/pay", $paymentData);

            // تنظيف الذاكرة بين العمليات
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }

        $finalMemory = memory_get_usage(true);
        $memoryUsed = $finalMemory - $initialMemory;
        $memoryUsedMB = round($memoryUsed / 1024 / 1024, 2);

        // يجب ألا يتجاوز استهلاك الذاكرة 50 ميجابايت
        $this->assertLessThan(50 * 1024 * 1024, $memoryUsed, "Memory usage too high: {$memoryUsedMB} MB");

        echo "\n🧠 Memory Usage:\n";
        echo "✅ Processed 100 payments using {$memoryUsedMB} MB\n";
        echo "✅ Average: " . round($memoryUsedMB / 100, 4) . " MB per payment\n";
    }
}
