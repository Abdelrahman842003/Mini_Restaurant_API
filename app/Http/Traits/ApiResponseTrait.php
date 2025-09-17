<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponseTrait
{
    /**
     * Generate a standardized API response.
     *
     * @param int $code HTTP status code
     * @param string|null $message Response message
     * @param mixed|null $errors Error details (array or null)
     * @param mixed|null $data Response data
     * @return JsonResponse
     */
    public function apiResponse(int $code = 200, ?string $message = null, $errors = null, $data = null): JsonResponse
    {
        // Initialize the response array
        $response = [
            'status' => $code,
            'message' => $message,
        ];

        // Add errors if they exist
        if (!is_null($errors)) {
            $response['errors'] = $errors;
        }

        // Add data if it exists
        if (!is_null($data)) {
            $response['data'] = $data;
        }

        return response()->json($response, $code);
    }

    /**
     * Generate a success response.
     */
    public function successResponse($data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return $this->apiResponse($code, $message, null, $data);
    }

    /**
     * Generate an error response.
     */
    public function errorResponse(string $message = 'Error', $errors = null, int $code = 400): JsonResponse
    {
        return $this->apiResponse($code, $message, $errors);
    }
}
