<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Order;
use App\Models\MenuItem;
use App\Models\Table;
use App\Models\Reservation;
use Laravel\Sanctum\Sanctum;

class SystemPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test System Performance with Multiple Concurrent Requests
     */
    public function test_system_handles_multiple_payment_requests()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create multiple orders
        $orders = Order::factory()->count(10)->create([
            'user_id' => $user->id,
            'status' => 'pending'
        ]);

        $startTime = microtime(true);

        // Simulate concurrent payment requests
        foreach ($orders as $order) {
            $response = $this->postJson("/api/v1/orders/{$order->id}/pay-legacy", [
                'payment_option' => 1
            ]);

            $response->assertStatus(201);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert that processing 10 payments takes less than 5 seconds
        $this->assertLessThan(5.0, $executionTime, 'Payment processing should be efficient');
    }

    /**
     * Test Memory Usage Efficiency
     */
    public function test_memory_usage_efficiency()
    {
        $initialMemory = memory_get_usage();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Create large dataset
        MenuItem::factory()->count(100)->create();
        Table::factory()->count(50)->create();

        $orders = Order::factory()->count(50)->create([
            'user_id' => $user->id
        ]);

        // Process multiple requests
        foreach ($orders->take(10) as $order) {
            $this->getJson("/api/v1/orders/{$order->id}/payment-status");
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease, 'Memory usage should be efficient');
    }

    /**
     * Test Database Query Efficiency
     */
    public function test_database_query_efficiency()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $order = Order::factory()->create(['user_id' => $user->id]);

        // Enable query logging
        \DB::enableQueryLog();

        // Make API request
        $this->getJson("/api/v1/orders/{$order->id}/payment-status");

        $queries = \DB::getQueryLog();

        // Should use minimal queries (less than 5)
        $this->assertLessThan(5, count($queries), 'Should use minimal database queries');
    }

    /**
     * Test Error Handling Robustness
     */
    public function test_error_handling_robustness()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Test various error scenarios
        $errorScenarios = [
            ['url' => '/api/v1/orders/999999/pay', 'data' => ['payment_option' => 1, 'payment_gateway' => 'stripe']],
            ['url' => '/api/v1/orders/1/pay', 'data' => ['payment_option' => 1, 'payment_gateway' => 'invalid']],
            ['url' => '/api/v1/orders/1/pay', 'data' => ['payment_option' => 999, 'payment_gateway' => 'stripe']],
        ];

        foreach ($errorScenarios as $scenario) {
            $response = $this->postJson($scenario['url'], $scenario['data']);

            // Should return proper error responses, not crash
            $this->assertContains($response->status(), [400, 404, 422]);
            $response->assertJsonStructure(['message']);
        }
    }

    /**
     * Test API Response Times
     */
    public function test_api_response_times()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $endpoints = [
            '/api/v1/payment-methods',
            '/api/v1/payment-gateways',
            '/api/v1/menu',
            '/api/v1/tables'
        ];

        foreach ($endpoints as $endpoint) {
            $startTime = microtime(true);

            $response = $this->getJson($endpoint);

            $endTime = microtime(true);
            $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

            $response->assertStatus(200);

            // Each endpoint should respond within 500ms
            $this->assertLessThan(500, $responseTime, "Endpoint {$endpoint} should respond quickly");
        }
    }
}
