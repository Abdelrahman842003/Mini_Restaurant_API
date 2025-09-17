<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\MenuItemController;
use App\Http\Controllers\Api\TableController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\WaitingListController;
use App\Http\Controllers\Api\PaymentCallbackController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Public authentication routes (outside v1 prefix)
Route::post('auth/login', [AuthController::class, 'login']);
Route::post('auth/register', [AuthController::class, 'register']);

// Protected authentication routes (outside v1 prefix)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// Public routes
Route::prefix('v1')->group(function () {
    // Menu management - Public access
    Route::get('menu', [MenuItemController::class, 'index']);

    // Table availability check - Public access
    Route::get('tables/availability', [TableController::class, 'checkAvailability']);

    // Table listing - Public access (for customers to see available tables)
    Route::get('tables', [TableController::class, 'index']);
});

// Protected routes
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    // Table Management - Full CRUD for authenticated users
    Route::get('tables/{id}', [TableController::class, 'show']);
    Route::post('tables', [TableController::class, 'store']);
    Route::put('tables/{id}', [TableController::class, 'update']);
    Route::delete('tables/{id}', [TableController::class, 'destroy']);

    // Reservations
    Route::post('reservations', [ReservationController::class, 'store']);
    Route::get('reservations', [ReservationController::class, 'index']);

    // Orders
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{id}', [OrderController::class, 'show']);

    // Payment Methods and Gateways
    Route::get('payment-methods', [PaymentController::class, 'getPaymentMethods']);
    Route::get('payment-gateways', [PaymentController::class, 'getPaymentGateways']);

    // Payment Processing - Enhanced with Factory Pattern
    Route::post('orders/{order}/pay', [PaymentController::class, 'processPayment']);

    // Payment Status and Invoice
    Route::get('orders/{order}/payment-status', [PaymentController::class, 'getPaymentStatus']);
    Route::get('invoices/{invoice}', [PaymentController::class, 'getInvoice']);

    // Payment Verification - Universal endpoint for all gateways
    Route::get('payment/{gateway}/verify/{transactionId}', [PaymentCallbackController::class, 'verifyPayment']);

    // Waiting List
    Route::post('waiting-list', [WaitingListController::class, 'join']);
    Route::get('waiting-list', [WaitingListController::class, 'index']);
    Route::delete('waiting-list/{id}', [WaitingListController::class, 'leave']);
});

// Public Payment Callbacks - No authentication required for external services
Route::prefix('payment')->group(function () {
    // Universal callback handler for PayPal only
    Route::post('{gateway}/callback', [PaymentCallbackController::class, 'handleCallback']);

    // PayPal specific redirect endpoints
    Route::get('paypal/success', [PaymentCallbackController::class, 'paypalSuccess']);
    Route::get('paypal/cancel', [PaymentCallbackController::class, 'paypalCancel']);
});

// Public Webhook endpoints - No authentication required for external services
Route::prefix('webhooks')->group(function () {
    // Reserved for future use
});

// Test PayPal configuration (only for development)
if (app()->environment(['local', 'development'])) {
    require __DIR__ . '/test-paypal.php';
}
