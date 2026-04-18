<?php 
namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Auth\AuthenticationException;
use Throwable;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

class Handler extends ExceptionHandler
{
    /**
     * Handle unauthenticated exceptions.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param \Illuminate\Http\Request  $request
     * @param \Throwable  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof TokenExpiredException) {
            return response()->json(['error' => 'Token has expired'], 401);
        }

        if ($exception instanceof TokenInvalidException) {
            return response()->json(['error' => 'Token is invalid'], 401);
        }

        if ($exception instanceof JWTException) {
            return response()->json(['error' => 'Token is not provided'], 401);
        }
        if ($exception instanceof PermissionException) {
            return response()->json([
                'error' => 'PermissionException',
                'message' => 'User does not have the right permissions.'
            ], 403);
        }
        if ($exception instanceof UnauthorizedException) {
            return response()->json([
                'error' => 'UnauthorizedException',
                'message' => 'User does not have the right permissions.'
            ], 403);
        }

        return parent::render($request, $exception);
    }
}
