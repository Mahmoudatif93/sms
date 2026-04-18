<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ResponseManager
{
    /**
     * Return a standard success JSON response.
     *
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    public function successResponse(
        string $message = 'Operation successful.',
        mixed  $data = null,
        int    $statusCode = 200,
        array  $headers = []
    ): JsonResponse
    {
        return $this->jsonResponse(true, $message, $data, $statusCode, $headers);
    }

    /**
     * Base JSON response builder.
     *
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    protected function jsonResponse(
        bool   $success,
        string $message = '',
        mixed  $data = null,
        int    $statusCode = 200,
        array  $headers = []
    ): JsonResponse
    {
        return new JsonResponse([
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ], $statusCode, $headers);
    }

    /**
     * Return a standard error JSON response.
     *
     * @param string $message
     * @param mixed $errors
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    public function errorResponse(
        string $message = 'An error occurred.',
        mixed  $errors = null,
        int    $statusCode = 400,
        array  $headers = []
    ): JsonResponse
    {
        return $this->jsonResponse(false, $message, $errors, $statusCode, $headers);
    }

    /**
     * Generic response method (alias for jsonResponse).
     *
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    public function response(
        bool   $success = true,
        string $message = '',
        mixed  $data = null,
        int    $statusCode = 200,
        array  $headers = []
    ): JsonResponse
    {
        return $this->jsonResponse($success, $message, $data, $statusCode, $headers);
    }
}
