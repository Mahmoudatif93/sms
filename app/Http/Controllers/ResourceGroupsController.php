<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Models\ResourceGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceGroupsController extends BaseApiController
{
    /**
     * Get all resource groups with optional filters and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $name = $request->query('name');
        $description = $request->query('description');

        $query = ResourceGroup::with('resources');

        if (!is_null($name)) {
            $query->where('name', 'LIKE', "%$name%");
        }

        if (!is_null($description)) {
            $query->where('description', 'LIKE', "%$description%");
        }

        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        $resourceGroups = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'Resource groups retrieved successfully.', $resourceGroups);
    }

    public function show(ResourceGroup $resourceGroup): JsonResponse
    {
        $resourceGroup->load('resources');

        return $this->response(true, 'Resource group details retrieved successfully.', $resourceGroup);
    }

    /**
     * Create a new resource group.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:resource_groups,name',
            'description' => 'nullable|string',
        ]);

        $resourceGroup = ResourceGroup::create($validated);

        return $this->response(true, 'Resource group created successfully.', $resourceGroup, 201);
    }

    /**
     * Attach an existing resource to a resource group.
     */
    public function attachResource(Request $request, ResourceGroup $resourceGroup): JsonResponse
    {
        $validated = $request->validate([
            'resource_id' => 'required|exists:resources,id',
        ]);

        $resourceGroup->resources()->attach($validated['resource_id']);

        return $this->response(true, 'Resource attached to the group successfully.');
    }

    /**
     * Detach a resource from a resource group.
     */
    public function detachResource(Request $request, ResourceGroup $resourceGroup, Resource $resource): JsonResponse
    {
        // Detach the resource directly using the route binding
        if (!$resourceGroup->resources->contains($resource->id)) {
            return $this->response(false, 'Resource does not belong to this group.', null, 400);
        }

        $resourceGroup->resources()->detach($resource->id);

        return $this->response(true, 'Resource detached from the group successfully.');
    }

    /**
     * Delete a resource group.
     */
    public function destroy(ResourceGroup $resourceGroup): JsonResponse
    {
        $resourceGroup->resources()->detach();
        $resourceGroup->delete();

        return $this->response(true, 'Resource group deleted successfully.');
    }
}
