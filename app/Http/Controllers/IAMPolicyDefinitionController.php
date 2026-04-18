<?php

namespace App\Http\Controllers;

use App\Models\IAMPolicyDefinition;
use App\Models\Resource;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

class IAMPolicyDefinitionController extends BaseApiController
{
    /**
     * Get a paginated list of IAM Policy Definitions.
     */
    public function index(Request $request): JsonResponse
    {
        // Get pagination and filter parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $effect = $request->query('effect');

        $query = IAMPolicyDefinition::orderByDesc('id')->with('resource');

        if (!is_null($effect)) {
            $query->where('effect', $effect);
        }

        $definitions = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'IAM Policy Definitions retrieved successfully', $definitions);
    }

    /**
     * Get details of a specific IAM Policy Definition.
     */
    public function show(IAMPolicyDefinition $definition): JsonResponse
    {
        $definition->load('resource');
        return $this->response(true, 'IAM Policy Definition retrieved successfully', $definition);
    }

    /**
     * Create a new IAM Policy Definition.
     */
    public function store(Request $request): JsonResponse
    {
        $allowedActions = [
            'GET|HEAD' => 'view',
            'POST' => 'create',
            'PUT' => 'edit',
            'PATCH' => 'edit',
            'PUT|PATCH' => 'edit',
            'DELETE' => 'delete',
        ];

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'effect' => 'required|string|in:allow,deny',
            'resource_id' => 'required|exists:resources,id',
        ]);

        $resource = Resource::findOrFail($validated['resource_id']);

        $method = strtoupper($resource->method); // Ensure case consistency

        // Match the method to an action directly
        $action = $allowedActions[$method] ?? null;

        // If no matching action is found, return an error
        if (!$action) {
            return $this->response(
                false,
                'The resource method does not map to a valid action.',
                null,
                400
            );
        }

        $policyDefinition = IAMPolicyDefinition::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'effect' => $validated['effect'],
            'action' => $action,
            'resource_id' => $validated['resource_id'],
        ]);

        return $this->response(true, 'IAM Policy Definition created successfully', $policyDefinition, 201);
    }

    /**
     * Update an existing IAM Policy Definition.
     */
    public function update(Request $request, IAMPolicyDefinition $definition): JsonResponse
    {
        $allowedActions = [
            'GET|HEAD' => 'view',
            'POST' => 'create',
            'PUT' => 'edit',
            'PATCH' => 'edit',
            'PUT|PATCH' => 'edit',
            'DELETE' => 'delete',
        ];

        // Validate the request
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'effect' => 'sometimes|required|string|in:allow,deny',
            'resource_id' => 'sometimes|required|exists:resources,id',
        ]);

        // Check if resource_id is being updated
        if (isset($validated['resource_id'])) {
            $resource = Resource::findOrFail($validated['resource_id']);
            $method = strtoupper($resource->method); // Ensure case consistency

            // Match the method to an action directly
            $action = $allowedActions[$method] ?? null;

            // If no matching action is found, return an error
            if (!$action) {
                return $this->response(
                    false,
                    'The resource method does not map to a valid action.',
                    null,
                    400
                );
            }

            // Add the derived action to the validated data
            $validated['action'] = $action;
        }

        // Update the IAM Policy Definition
        $definition->update($validated);

        return $this->response(true, 'IAM Policy Definition updated successfully', $definition);
    }

    /**
     * Delete an IAM Policy Definition.
     */
    public function destroy(IAMPolicyDefinition $definition): JsonResponse
    {
        try {
            // Check if the policy definition has an associated resource and dissociate it
            if ($definition->resource()->exists()) {
                $definition->resource()->dissociate();
                $definition->save(); // Save after dissociation
            }

            // Detach any associated policies (clean pivot table)
            $definition->policies()->detach();

            // Delete the policy definition
            $definition->delete();

            return $this->response(true, 'IAM Policy Definition deleted successfully');
        } catch (Exception $e) {
            // Log the error for debugging
            Log::error('Error deleting IAM Policy Definition: ' . $e->getMessage());

            return $this->response(false, 'Failed to delete IAM Policy Definition', null, 500);
        }
    }
}
