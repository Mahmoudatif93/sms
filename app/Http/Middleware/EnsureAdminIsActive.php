<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\BaseApiController;

class EnsureAdminIsActive extends BaseApiController
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


        $supervisor = Auth::guard('admin')->user();



        if ($supervisor) {
            // Check if the authenticated user is active
            if ( $supervisor->otp == null) {
                return $next($request);
            } else {
                Auth::guard('admin')->logout();
            }
        }


        // If the user is not authenticated or not active, redirect to a specific route or page
        return $this->response(false, 'Unauthenticated.', null, 401);

    }
}
