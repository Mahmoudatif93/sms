<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\AccessKey;
use App\Models\User;
use App\Models\IAMRole;
use App\Traits\ResourceManager;
use Str;

class AuthenticateUserOrAccessKey
{
    use ResourceManager;

    public function handle(Request $request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        $accessor = $this->authenticateUser();


        // If user JWT auth failed, check for AccessKey
        if (!$accessor && Str::startsWith($authHeader, 'AccessKey ')) {
            $token = Str::after($authHeader, 'AccessKey ');

            if (Str::startsWith($token, 'ak_') && Str::contains($token, '.')) {
                [$suffix, $secret] = explode('.', Str::after($token, 'ak_'));

                $accessKey = AccessKey::where('suffix', $suffix)
                    ->where('token', $secret) // Plaintext match
                    ->first();

                if ($accessKey) {
                    $request->attributes->set('access_key', $accessKey);
                    $request->attributes->set('accessor', $accessKey);
                    $accessor = $accessKey;
                }
            }
        }

        if ($accessor) {
            if ($this->canAccessURI($request, $accessor)) {
                $request->attributes->set('accessor', $accessor);
                return $next($request);
            }
            return response()->json(['error' => 'You do not have permission to access this resource.'], 403);
        }

        return response()->json(['error' => 'Invalid authentication token.'], 401);
    }

    /**
     * Authenticate the user based on JWT token
     */
    protected function authenticateUser(): ?User
    {
        try {
            $authenticatedUser = auth('api')->user(); // Explicitly use Passport's 'api' guard
            return empty($authenticatedUser) ? null :  User::whereId($authenticatedUser->getAuthIdentifier())->first();


        } catch (Exception $e) {
            return null;
        }
    }
}
