<?php

namespace App\Http\Controllers;

use App\Models\AccessKey;
use App\Models\User;
use Illuminate\Http\JsonResponse;

use Illuminate\Http\Request;
use OpenApi\Annotations as OA;


/**
 * @OA\OpenApi(
 *     @OA\Info(
 *         title="Dreams CRM API",
 *         version="1.0.0",
 *         description="API documentation for Dreams CRM"
 *     ),
 *
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST_PROD,
 *         description="Production Server"
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST_STAGING,
 *         description="Staging Server"
 *     ),
 *     @OA\Server(
 *         url=L5_SWAGGER_CONST_HOST_DEV,
 *         description="Development Server"
 *     )
 * )
 */


/**
 * @OA\SecurityScheme(
 *     securityScheme="BearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Use JWT in the format: Bearer {token}"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="AccessKeyAuth",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Use Access Key in the format: AccessKey ak_xxx.yyy"
 * )
 */



class BaseApiController extends Controller
{
    /**
     * Simplified response method without locale.
     *
     * @param bool $success
     * @param string $message
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     * @return JsonResponse
     */
    protected function response(
        bool   $success = true,
        string $message = '',
        mixed  $data = null,
        int    $statusCode = 200,
        array  $headers = []
    ): JsonResponse
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        return new JsonResponse($response, $statusCode, $headers);
    }

    protected function paginateResponse( bool   $success = true,
    string $message = '',
    mixed  $items = null,
    int    $statusCode = 200,
    array  $headers = [],
    array $additional = [] // 👈 Add this
    )
    {
        $response = [
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ];

        // ✅ Safely merge additional fields like 'unread_count'
        return response()->json(array_merge($response, $additional), $statusCode, $headers);
    }

    protected function getAccessor(Request $request): User|AccessKey|null
    {
        return $request->attributes->get('accessor');
    }


}
