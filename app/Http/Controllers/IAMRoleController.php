<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\IAMRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class IAMRoleController extends BaseApiController
{
    /**
     * List all roles with pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $search = $request->get('search');

        $query = IAMRole::with('policies');

        // Apply search filter
        if ($search) {
            $query->where('name', 'LIKE', "%$search%")
                ->orWhere('description', 'LIKE', "%$search%")
                ->orWhere('type', 'LIKE', "%$search%");
        }

        $roles = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'Roles retrieved successfully', $roles);
    }

    /**
     * Show details of a specific role.
     */
    public function show(IAMRole $role): JsonResponse
    {
        $role->load('policies');
        return $this->response(true, 'Role retrieved successfully', $role);
    }

    /**
     * Create a new role.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'policies' => 'nullable|array|min:1',
            'policies.*' => 'exists:iam_policies,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $role = IAMRole::create([
            'name' => $request->name,
            'description' => $request->description,
            'type' => 'managed',
        ]);

        if ($request->has('policies')) {
            $role->policies()->attach($request->policies);
        }

        return $this->response(true, 'Role created successfully', $role, 201);
    }

    /**
     * Update an existing role.
     */
    public function update(Request $request, IAMRole $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'policies' => 'nullable|array|min:1',
            'policies.*' => 'exists:iam_policies,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $role->update($request->only(['name', 'description']));

        if ($request->has('policies')) {
            $role->policies()->sync($request->policies);
        }

        return $this->response(true, 'Role updated successfully', $role);
    }

    /**
     * Delete a role.
     */
    public function destroy(IAMRole $role): JsonResponse
    {
        $role->policies()->detach();
        $role->delete();

        return $this->response(true, 'Role Deleted Successfully', null, 204);
    }

    /**
     * Attach a policy to a role.
     */
    public function attachPolicy(Request $request, IAMRole $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'policy_id' => 'required|exists:iam_policies,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $policyId = $request->input('policy_id');

        if ($role->policies()->where('iam_policy_id', $policyId)->exists()) {
            return $this->response(false, 'Policy already attached to this role.', null, 400);
        }

        $role->policies()->attach($policyId);

        return $this->response(true, 'Policy attached to role successfully', $role->load('policies'));
    }

    /**
     * Detach a policy from a role.
     */
    public function detachPolicy(Request $request, IAMRole $role): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'policy_id' => 'required|exists:iam_policies,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $policyId = $request->input('policy_id');

        if (!$role->policies()->where('iam_policy_id', $policyId)->exists()) {
            return $this->response(false, 'Policy not attached to this role.', null, 400);
        }

        $role->policies()->detach($policyId);

        return $this->response(true, 'Policy detached from role successfully', $role->load('policies'));
    }
}
