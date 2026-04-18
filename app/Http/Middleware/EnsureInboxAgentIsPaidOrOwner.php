<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Traits\ResponseManager;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInboxAgentIsPaidOrOwner
{

    use ResponseManager;
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $workspace = $request->route('workspace');

        if (!$workspace || !$workspace->organization) {
            return $this->errorResponse('Invalid workspace or organization context.', null, 403);
        }

        $user = $this->authenticateUser();

        if (!$user) {
            return $this->errorResponse('Unauthenticated.', null, 401);
        }

        $organization = $workspace->organization;

        // Allow access if the user is an organization owner
        if ($user->isOrganizationOwner($organization)) {
            return $next($request);
        }

        // Allow access if the user is a paid inbox agent in this workspace
        if (
            $user->isMemberOfWorkspace($workspace) &&
            $user->isInboxAgentBillingActive()
        ) {
            return $next($request);
        }

        return $this->errorResponse(
            'Access denied. Only paid inbox agents or organization owners are allowed.',
            null,
            403
        );
    }

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
