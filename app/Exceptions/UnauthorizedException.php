<?php 

namespace App\Exceptions;

use Exception;

class UnauthorizedException extends Exception
{
    public function render($request)
    {
        return response()->json([
            'error' => 'UnauthorizedException',
            'message' => 'User does not have the right permissions.'
        ], 403); // 403 Forbidden status code
    }
}
