<?php

namespace App\Http\Middleware;
use Exception;
use JWTAuth;
use Closure;
use Tymon\JWTAuth\Http\Middleware\BaseMiddleware;
class jwtAuthMiddleware extends BaseMiddleware
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

        try{
            JWTAuth::parseToken()->authenticate();
        }catch(Exception $exception){
            if($exception instanceof \Tymon\JWTAuth\Exceptions\TokenInvalidException ){
                return response()->json(['status'=>'Invalid Token']);
            }
            else if($exception instanceof \Tymon\JWTAuth\Exceptions\TokenExpiredException){
                return response()->json(['status'=>'Token Expired']);
            }else{
                return response()->json(['status'=>'Token Is not Found']);
            }
        }

        return $next($request);


    }
}
