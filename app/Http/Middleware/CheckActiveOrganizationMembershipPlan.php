<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckActiveOrganizationMembershipPlan
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');


        // Retrieve the organization
        $organization = Organization::find($workspace->organization_id);

        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        // Check for membership plans
        $membershipPlan = $organization->membershipPlans()->first();
        if ($membershipPlan ) {
            if (!$organization->isMembershipBillingActive($membershipPlan)) {
                return response()->json(['error' => 'Membership plan is not active'], 403);
            }
        }

        // Check for hosting plans
        $hasHostingPlans = $organization->hostingPlans()->exists();
        if ($hasHostingPlans) {
            $activeHosting = $organization->hostingPlans()->where('is_active', '=', true)->first();
            if (empty($activeHosting)) {
                return response()->json(['error' => 'Hosting plan is not active'], 403);
            }
        }

        return $next($request);
    }
}
