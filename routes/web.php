<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

// Mock PayPal approval route - redirects immediately to simulate PayPal flow
Route::get('/mock-paypal-approval', function (Request $request) {
    // Get the mock token from the URL
    $token = $request->query('token');

    if (!$token) {
        return response()->json([
            'error' => 'Missing token parameter'
        ], 400);
    }

    // Log the mock approval for debugging
    \Illuminate\Support\Facades\Log::info('Mock PayPal approval - immediate redirect', [
        'token' => $token,
        'simulating' => 'PayPal automatic redirect'
    ]);

    // Build the callback URL that simulates PayPal returning the user
    $callbackUrl = config('app.url') . '/api/payment/paypal/success?token=' . $token . '&PayerID=MOCK_PAYER_ID';

    // Immediate redirect to simulate PayPal approval process
    return redirect($callbackUrl);
});
