<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\IAMRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Validator;

class IAMRolesController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/iam-roles",
     *     summary="List all roles in an organization with pagination",
     *     tags={"IAM Roles"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of roles per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of roles",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IAMRole")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", description="Total number of items"),
     *                 @OA\Property(property="per_page", type="integer", description="Number of items per page"),
     *                 @OA\Property(property="current_page", type="integer", description="Current page number"),
     *                 @OA\Property(property="last_page", type="integer", description="Last page number"),
     *                 @OA\Property(property="from", type="integer", description="Starting item index on this page"),
     *                 @OA\Property(property="to", type="integer", description="Ending item index on this page")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function index(Request $request, $organizationId): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $all = filter_var($request->get('all'), FILTER_VALIDATE_BOOLEAN); // Ensures 'true'/'1' works

        $query = IAMRole::with('policies');

        if ($all) {
            // Include both organization roles and managed roles
            $query->where(function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId)
                    ->orWhere('type', 'managed');
            });
        } else {
            // Only organization-specific roles
            $query->where('organization_id', $organizationId);
        }

        $roles = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform roles into response format
        $response = $roles->getCollection()->map(function ($role) {
            return new \App\Http\Responses\IAMRole($role);
        });

        // Replace the collection with the transformed data
        $roles->setCollection($response);

        return $this->paginateResponse(true, 'Roles in the organization', $roles);
    }


    /**
     * @OA\Post(
     *     path="/api/organizations/{organizationId}/iam-roles",
     *     summary="Create a new role in an organization",
     *     tags={"IAM Roles"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Name of the role"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Description of the role",
     *                 nullable=true
     *             ),
     *             @OA\Property(
     *                 property="policies",
     *                 type="array",
     *                 description="List of policy IDs to attach to the role",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Role created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/IAMRole")
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Organization not found")
     * )
     */
    public function store(Request $request, $organizationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'policies' => 'nullable|array|min:1',
            'policies.*' => 'exists:iam_policies,id', // Validate that each policy exists
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Create a new role with validated data from the request
        $role = IAMRole::create([
            'name' => $request->name,
            'description' => $request->description,
            'organization_id' => $organizationId,
            'type' => 'organization',
        ]);

        // Attach policies if provided
        if ($request->has('policies')) {
            $role->policies()->attach($request->policies);
        }

        return $this->response(true, 'IAMRole Created Successfully', new \App\Http\Responses\IAMRole($role), 201);
    }


    /**
     * Show a single role
     */
    public function show($organizationId, $roleId)
    {
        $role = IAMRole::with('policies')->findOrFail($roleId);
        return response()->json(new \App\Http\Responses\IAMRole($role));
    }

    /**
     * @OA\Patch(
     *     path="/api/organizations/{organizationId}/iam-roles/{roleId}",
     *     summary="Update an existing role",
     *     tags={"IAM Roles"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="ID of the role",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Name of the role",
     *                 example="Manager"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Description of the role",
     *                 nullable=true,
     *                 example="Role with management privileges"
     *             ),
     *             @OA\Property(
     *                 property="policies",
     *                 type="array",
     *                 description="List of policy IDs to sync with the role",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Role updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/IAMRole")
     *     ),
     *     @OA\Response(response=400, description="Validation error"),
     *     @OA\Response(response=404, description="Organization or Role not found")
     * )
     */
    public function update(Request $request, $organizationId, $roleId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'policies' => 'nullable|array|min:1',
            'policies.*' => 'exists:iam_policies,id', // Validate that each policy exists
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $role = IAMRole::findOrFail($roleId);
        $role->update($request->only(['name', 'description']));

        // Sync policies if provided
        if ($request->has('policies')) {
            $role->policies()->sync($request->policies);
        }

        return response()->json(new \App\Http\Responses\IAMRole($role), 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{organizationId}/iam-roles/{roleId}",
     *     summary="Delete a role in an organization",
     *     tags={"IAM Roles"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="roleId",
     *         in="path",
     *         required=true,
     *         description="ID of the role to delete",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Role deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Organization or Role not found")
     * )
     */
    public function destroy($organizationId, $roleId)
    {
        $role = IAMRole::findOrFail($roleId);
        $role->delete();

        return response()->json(null, 204);
    }

    public function getAllRoles(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $search = $request->get('search');

        $query = IAMRole::with('policies')->where('type', '=', 'managed');

        // Apply search filter
        if ($search) {
            $query->where('name', 'LIKE', "%$search%")
                ->orWhere('description', 'LIKE', "%$search%")
                ->orWhere('type', 'LIKE', "%$search%");
        }

        $roles = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'Roles retrieved successfully', $roles);
    }
}
