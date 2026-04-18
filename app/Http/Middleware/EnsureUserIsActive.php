<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseApiController;

class EnsureUserIsActive extends BaseApiController
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Check if the user is authenticated

        if (Auth::check()) {
            return $next($request);
            // Check if the authenticated user is active
            $user = Auth::user();
            if ($user->active == 1) {
                return $next($request);
            } 
        }
        // If the user is not authenticated or not active, redirect to a specific route or page
        return $this->response(false, 'Unauthenticated.', null, 401);
    }
}
