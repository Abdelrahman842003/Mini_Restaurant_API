<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BasePaymentService
{
    protected $base_url;
    protected array $header;

    /**
     * Build HTTP request to payment gateway
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Endpoint URL
     * @param array|null $data Request data
     * @param string $type Request type (json or form_params)
     * @return \Illuminate\Http\JsonResponse
     */
    protected function buildRequest($method, $url, $data = null, $type = 'json'): \Illuminate\Http\JsonResponse
    {
        try {
            // Log the request for debugging
            Log::info('Payment Gateway Request', [
                'method' => $method,
                'url' => $this->base_url . $url,
                'type' => $type,
                'data_size' => is_array($data) ? count($data) : 0
            ]);

            // Send HTTP request with headers
            $response = Http::withHeaders($this->header)
                ->timeout(30) // Add timeout
                ->retry(2, 1000) // Retry 2 times with 1 second delay
                ->send($method, $this->base_url . $url, [
                    $type => $data
                ]);

            $responseData = [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'headers' => $response->headers(),
                'request_timestamp' => now()->toISOString()
            ];

            // Log successful response
            Log::info('Payment Gateway Response', [
                'status' => $response->status(),
                'success' => $response->successful(),
                'response_size' => strlen($response->body())
            ]);

            return response()->json($responseData, $response->status());

        } catch (Exception $e) {
            // Enhanced error logging
            Log::error('Payment Gateway Request Failed', [
                'method' => $method,
                'url' => $this->base_url . $url,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'timestamp' => now()->toISOString()
            ]);

            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Validate gateway configuration
     */
    protected function validateConfiguration(): bool
    {
        if (empty($this->base_url)) {
            throw new Exception('Gateway base URL is not configured');
        }

        if (empty($this->header)) {
            throw new Exception('Gateway headers are not configured');
        }

        return true;
    }

    /**
     * Get gateway base URL
     */
    public function getBaseUrl(): string
    {
        return $this->base_url;
    }

    /**
     * Get gateway headers (without sensitive data)
     */
    public function getHeaders(): array
    {
        $headers = $this->header;

        // Hide sensitive authorization data
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = 'Bearer ***';
        }

        return $headers;
    }
}
