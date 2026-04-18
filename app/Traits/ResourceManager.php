<?php

namespace App\Traits;

use App\Models\Organization;
use App\Models\Workspace;
use App\Models\User;
use App\Models\AccessKey;
use Illuminate\Http\Request;

trait ResourceManager
{
    public function mapMethodToAction($method): string
    {
        return match (strtoupper($method)) {
            'POST' => 'create',
            'GET|HEAD', 'GET', 'HEAD' => 'view',
            'PUT|PATCH','PATCH', 'PUT' => 'edit',
            'DELETE' => 'delete',
            default => 'any',
        };

    }

    /**
     * Check if user or access key can access the current request
     */
    protected function canAccessURI(Request $request, User|AccessKey $accessor): bool
    {
        if ($accessor instanceof AccessKey) {
            // Short-circuit and defer to model logic
            $method = $request->method();
            $uri = $request->route()->uri();
            $method = $method === "GET" || $method === "HEAD" ? "GET|HEAD" : $method;

            return $accessor->canAccessURI($uri, $method);
        }

        $organizationId = $this->getOrganizationIdFromRequest($request);

        // If organization is in request and accessor is a User, check ownership first
        if ($organizationId && $accessor instanceof User) {
            // If user is organization owner, grant full access
            if (
                $accessor->ownedOrganizations()
                    ->where('id', $organizationId)
                    ->exists()
            ) {
                return true;
            }
        }

        // For access keys, check organization membership
        if (!$this->canAccessOrganizationInRequest($accessor,$organizationId)) {
            return false;
        }

        // Check workspace access
        $workspaceId = $this->getWorkspaceIdFromRequest($request);
        if (!$this->canAccessWorkspaceInRequest($accessor,$workspaceId)) {
            return false;
        }

        // Check role-based URI permissions
        $method = $request->method();
        $uri = $request->route()->uri();
        $method = $method === "GET" || $method === "HEAD" ? "GET|HEAD" : $method;

        return $accessor->roles()
            ->whereHas('policies', function ($query) use ($uri, $method) {
                $query->whereHas('definitions', function ($query) use ($uri, $method) {
                    $query->whereHas('resource', function ($query) use ($uri, $method) {
                        $query->where('uri', $uri)->where('method', '=', $method);
                    });
                });
            })
            ->exists();
    }

    protected function canAccessOrganizationInRequest(User|AccessKey $accessor,?string $organizationId): bool
    {
        if (!$organizationId) {
            return true; // No organization in request, skip check
        }
        if ($accessor instanceof AccessKey) {
            // Access key can only access its own organization
            return $accessor->organization_id === $organizationId;
        }
        $organization = Organization::find($organizationId);
        if (!$organization) {
            return false;
        }

        // Check if user is organization owner
        if ($organization->owner_id === $accessor->id) {
            return true;
        }

        // Check if user is member of any workspace in organization
        return $organization->workspaces()
            ->whereHas('users', function ($query) use ($accessor) {
                $query->where('user.id', $accessor->id)
                    ->where('workspace_users.status', 'active');
            })->exists();
    }


    /**
     * Check if user has access to workspace in the current request
     */
    protected function canAccessWorkspaceInRequest(User|AccessKey $accessor,?string $workspaceId): bool
    {
        if (!$workspaceId) {
            return true; // No workspace in request, skip check
        }

        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            return false;
        }

        if ($accessor instanceof AccessKey) {
            // Access key can only access workspaces in its organization
            return $workspace->organization_id === $accessor->organization_id;
        }

        // Check if user is organization owner
        if ($workspace->organization->owner_id === $accessor->id) {
            return true;
        }

        // Check if user is workspace member
        return $workspace->users()
            ->where('user.id', $accessor->id)
            ->where('workspace_users.status', 'active')
            ->exists();
    }

    /**
     * Extract organization ID from request
     */
    protected function getOrganizationIdFromRequest(Request $request): ?string
    {
        // Try to get from route parameter
        if ($request->route('organization')) {
            return $request->route('organization')?->id;
        }

        // Try to get from request parameter
        if ($request->input('organization_id')) {
            return $request->input('organization_id');
        }

        // Try to get from workspace's organization if workspace is present
        if ($request->route('workspace')) {
            return $request->route('workspace')?->organization_id;
        }

        return null;
    }

    /**
     * Extract workspace ID from request
     */
    protected function getWorkspaceIdFromRequest(Request $request): ?string
    {
        // Try to get from route parameter
        if ($request->route('workspace')) {
            return $request->route('workspace')?->id;
        }

        // Try to get from request parameter
        if ($request->input('workspace_id')) {
            return $request->input('workspace_id');
        }

        return null;
    }
}
