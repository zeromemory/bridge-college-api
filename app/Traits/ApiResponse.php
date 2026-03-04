<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

trait ApiResponse
{
    /**
     * Return a success response.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
        ], $status);
    }

    /**
     * Return an error response.
     */
    protected function error(
        string $message = 'Something went wrong',
        string $errorCode = 'SERVER_ERROR',
        int $status = 500,
        array $errors = [],
        mixed $data = null,
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }
}
