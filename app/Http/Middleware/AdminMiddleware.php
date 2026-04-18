<?php

namespace App\Http\Middleware;

use App\Http\Controllers\BaseApiController;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminMiddleware extends BaseApiController
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }
        // Check if the user is authenticated
        $supervisor = Auth::guard('admin')->user();

        if (!$supervisor) {
            Auth::guard('admin')->logout();
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);

        }



        return $next($request);
    }
}
