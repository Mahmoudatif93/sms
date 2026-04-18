<?php

namespace App\Http\Controllers;

use App\Models\IAMPolicy;
use App\Models\Organization;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class IAMPoliciesController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/iam-policies",
     *     summary="Get all IAM policies for an organization with pagination",
     *     tags={"IAM Policies"},
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
     *         description="Number of policies per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of IAM Policies",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IamPolicy")),
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
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found")
     *         )
     *     )
     * )
     */
    public function getAllForOrganization(Request $request, Organization $organization): JsonResponse
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $all = filter_var($request->get('all'), FILTER_VALIDATE_BOOLEAN); // Ensures 'true'/'1' works

        $query = IAMPolicy::with('definitions.resource');

        $organizationId = $organization->id;

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

        $policies = $query->paginate($perPage, ['*'], 'page', $page);


        return $this->paginateResponse(true, 'IAM Policies retrieved successfully for organization', $policies);
    }

    /**
     * @OA\Get(
     *     path="/api/iam-policies",
     *     summary="Get all managed IAM policies",
     *     tags={"IAM Policies"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of policies per page for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter policies by type",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of IAM Policies",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/IamPolicy")),
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
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = IAMPolicy::query();

        // Apply filters if any
        if ($request->has('name')) {
            $query->where('name', 'LIKE', '%' . $request->query('name') . '%');
        }

        $policies = $query->with('definitions.resource')->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'IAM Policies retrieved successfully', $policies);
    }

    /**
     * @OA\Get(
     *     path="/api/iam-policies/{id}",
     *     summary="Get IAM policy by ID",
     *     tags={"IAM Policies"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the IAM Policy",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="IAM Policy details",
     *         @OA\JsonContent(ref="#/components/schemas/IamPolicy")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Policy not found"
     *     )
     * )
     */
    public function getById(IAMPolicy $iamPolicy): JsonResponse
    {

        return response()->json(new \App\Http\Responses\IAMPolicy($iamPolicy), 200);
    }

    /**
     * @OA\Patch(
     *     path="/api/iam-policies/{id}",
     *     summary="Update IAM policy",
     *     tags={"IAM Policies"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the IAM Policy",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Updated Policy Name"),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="IAM Policy updated",
     *         @OA\JsonContent(ref="#/components/schemas/IamPolicy")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Policy not found"
     *     )
     * )
     */
    public function update(Request $request, IAMPolicy $iamPolicy): JsonResponse
    {

        // Validate and update policy data
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        $iamPolicy->update($validatedData);

        return response()->json(new \App\Http\Responses\IAMPolicy($iamPolicy), 200);
    }

    /**
     * @OA\Delete(
     *     path="/api/iam-policies/{id}",
     *     summary="Delete IAM policy",
     *     tags={"IAM Policies"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the IAM Policy",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Policy deleted"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Policy not found"
     *     )
     * )
     */
    public function destroy(IAMPolicy $iamPolicy): JsonResponse
    {
        // Detach all associated definitions
        $iamPolicy->definitions()->detach();

        // Delete the IAM Policy
        $iamPolicy->delete();

        return $this->response(true, 'IAM Policy deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/iam-policies",
     *     summary="Create a custom IAM policy",
     *     tags={"IAM Policies"},
     *     security={{ "apiAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "organization_id", "definitions"},
     *             @OA\Property(property="organization_id", type="string", example="org-123456"),
     *             @OA\Property(property="name", type="string", example="New Custom Policy"),
     *             @OA\Property(property="description", type="string", example="A description for the custom policy"),
     *             @OA\Property(
     *                 property="definitions",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="effect", type="string", example="allow", enum={"allow", "deny"}),
     *                     @OA\Property(property="action", type="string", example="view", enum={"view", "edit", "delete", "any", "update"}),
     *                     @OA\Property(
     *                         property="resources",
     *                         type="array",
     *                         @OA\Items(
     *                             type="string",
     *                             description="Resource ID from resources table",
     *                             example="resource-id-123"
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="IAM Policy created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/IamPolicy")
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object", example={
     *                 "definitions.0.resources.0": {"The selected resource is invalid."}
     *             })
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found")
     *         )
     *     )
     * )
     */
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'definitions' => 'required|array|min:1',
            'definitions.*' => 'required|exists:iam_policy_definitions,id|distinct',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create the IAM Policy
            $policy = IAMPolicy::create([
                'name' => $request->input('name'),
                'description' => $request->input('description', ''),
                'organization_id' => $organization->id,
                'type' => 'custom',
                'scope' => 'organization',
            ]);

            // Attach definitions
            $definitions = $request->input('definitions');
            foreach ($definitions as $definition) {
                $policy->definitions()->attach($definition);
            }

            // Commit transaction
            DB::commit();

            // Load the definitions for the response
            $policy->load('definitions');

            return $this->response(true, 'IAM Policy created successfully', $policy, 201);

        } catch (Exception $e) {
            // Rollback transaction in case of error
            DB::rollBack();
            return $this->response(false, 'Failed to create IAM Policy', ['error' => $e->getMessage()], 500);
        }
    }

    public function create(Request $request, Organization $organization): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'definitions' => 'required|array|min:1',
            'definitions.*' => 'required|exists:iam_policy_definitions,id|distinct',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
        }

        // Begin transaction
        DB::beginTransaction();

        try {
            // Create the IAM Policy
            $policy = IAMPolicy::create([
                'name' => $request->input('name'),
                'description' => $request->input('description', ''),
                'type' => 'custom',
                'organization_id' => $organization->id,
                'scope' => 'organization',
            ]);

            // Attach definitions
            $definitions = $request->input('definitions');
            foreach ($definitions as $definition) {
                $policy->definitions()->attach($definition);
            }

            // Commit transaction
            DB::commit();

            // Load the definitions for the response
            $policy->load('definitions');

            return $this->response(true, 'IAM Policy created successfully', $policy, 201);

        } catch (Exception $e) {
            // Rollback transaction in case of error
            DB::rollBack();
            return $this->response(false, 'Failed to create IAM Policy', ['error' => $e->getMessage()], 500);
        }
    }

    public function attachDefinitions(Request $request, IAMPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'definition_ids' => 'required|array|min:1',
            'definition_ids.*' => 'exists:iam_policy_definitions,id|distinct',
        ]);

        $definitionIds = $validated['definition_ids'];

        // Check for already attached definitions
        $alreadyAttached = $policy->definitions()
            ->whereIn('iam_policy_definition_id', $definitionIds)
            ->pluck('iam_policy_definition_id')
            ->toArray();

        if (!empty($alreadyAttached)) {
            return $this->response(false, 'Some definitions are already attached to this policy.', [
                'already_attached' => $alreadyAttached,
            ], 400);
        }

        // Attach definitions
        $policy->definitions()->attach($definitionIds);

        return $this->response(true, 'Definitions attached successfully', $policy->load('definitions'));
    }

    /**
     * Detach a definition from a policy.
     */
    public function detachDefinitions(Request $request, IAMPolicy $policy): JsonResponse
    {
        $validated = $request->validate([
            'definition_ids' => 'required|array|min:1',
            'definition_ids.*' => 'exists:iam_policy_definitions,id|distinct',
        ]);

        $definitionIds = $validated['definition_ids'];

        // Check for definitions not attached
        $notAttached = array_diff($definitionIds, $policy->definitions()->pluck('iam_policy_definition_id')->toArray());

        if (!empty($notAttached)) {
            return $this->response(false, 'Some definitions are not attached to this policy.', [
                'not_attached' => $notAttached,
            ], 400);
        }

        // Detach definitions
        $policy->definitions()->detach($definitionIds);

        return $this->response(true, 'Definitions detached successfully', $policy->load('definitions'));
    }

    public function getAll(Request $request): JsonResponse
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Fetch paginated IAM policies for the organization
        $policies = IAMPolicy::with('definitions.resource')->where('type', '=', 'managed')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(true, 'IAM Policies retrieved successfully', $policies);
    }


}
