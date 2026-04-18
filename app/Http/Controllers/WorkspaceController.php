<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Organization;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use App\Services\WalletAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;
use App\Traits\ChannelManager;
use Str;

class WorkspaceController extends BaseApiController
{
    use ChannelManager;

    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/workspaces",
     *     summary="List all workspaces for an organization",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of workspaces per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number for pagination",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of workspaces",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Workspaces retrieved successfully for organization"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Workspace")
     *             ),
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
    public function index(Request $request, Organization $organization): JsonResponse
    {
        $user = $request->user();


        // Check if user is owner of the organization or if user is an admin
        $isOwnerOrAdmin = $organization->owner_id === $user->id || Auth::guard('admin')->check();

        // Get search parameters
        $name = $request->query('name');
        $status = $request->query('status');

        // Build query with filters for the given organization
        $query = Workspace::where('organization_id', $organization->id);
        if (!$isOwnerOrAdmin) {
            $query->whereHas('users', function ($q) use ($user) {
                $q->where('user.id', $user->id)
                    ->where('workspace_users.status', WorkspaceUser::STATUS_ACTIVE);
            });
        }

        if (!is_null($name)) {
            $query->where('name', 'LIKE', "%$name%");
        }

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Fetch paginated workspaces
        $workspaces = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform workspaces to response format
        $response = $workspaces->getCollection()->map(function ($workspace) {
            return new \App\Http\Responses\Workspace($workspace);
        });

        // Replace the collection with the transformed data
        $workspaces->setCollection($response);

        return $this->paginateResponse(true, 'Workspaces retrieved successfully for organization', $workspaces);
    }


    /**
     * @OA\Post(
     *     path="/api/organizations/{organizationId}/workspaces",
     *     summary="Create a new workspace for an organization",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             required={"name", "description"},
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Name of the workspace",
     *                 example="Workspace Name"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Description of the workspace",
     *                 example="Workspace description."
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workspace created successfully for organization",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Workspace created successfully for organization"),
     *             @OA\Property(property="data", ref="#/components/schemas/Workspace")
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
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {
     *                         "type": "string"
     *                     }
     *                 },
     *                 description="Validation errors"
     *             )
     *         )
     *     )
     * )
     */

    public function store(Request $request, Organization $organization): JsonResponse
    {
        try {
            $validator = Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'description' => 'required|string'
                ]
            );

            if ($validator->fails()) {
                if ($validator->fails()) {
                    return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
                }
            }
            return \DB::transaction(function () use ($request, $organization) {
                $workspace = $organization->workspaces()->create([
                    'id' => Str::uuid(),
                    'name' => $request->input('name'),
                    'description' => $request->input('description') ?? null,
                    'status' => 'active'
                ]);

                if ($organization->workspaces()->count() === 1) {
                    try {
                        $this->handleFirstWorkspaceSetup($workspace, $organization);
                    } catch (\Exception $e) {
                        \Log::error('Error setting up default SMS channel: ' . $e->getMessage());
                    }
                }
                return $this->response(true, 'Workspace created successfully', $workspace);
            });
        } catch (\Exception $e) {
            \Log::error('Workspace creation failed: ' . $e->getMessage());
            return $this->response(false, 'Failed to create workspace', null, 500);
        }
    }


    /**
     * Handle setup specific to the first workspace of an organization
     */
    private function handleFirstWorkspaceSetup(Workspace $workspace, Organization $organization): void
    {
        // Check if this is truly the first non-deleted workspace
        $totalWorkspaces = $organization->workspaces()
            ->withTrashed()
            ->count();

        $user = \Auth::user();
        $user->workspace_id = $workspace->id;
        $user->save();

        if ($totalWorkspaces > 1) {
            $this->handleSubsequentWorkspaceSetup($workspace, $organization);
            return;
        }

        // Check for existing channels including soft-deleted ones
        $existingChannels = $workspace->channels()
            ->withTrashed()
            ->count();

        if ($existingChannels === 0) {
            $this->createDefaultSmsChannel($workspace);
        }

        $this->handleSubsequentWorkspaceSetup($workspace, $organization);
    }

    /**
     * Handle setup for subsequent workspaces, including wallet assignments
     */
    private function handleSubsequentWorkspaceSetup(Workspace $workspace, Organization $organization): void
    {
        $serviceOtherId = MService::where('name', EnumService::OTHER)->value('id');
        $serviceSmsId = MService::where('name', EnumService::SMS)->value('id');

        // Fetch both wallets first to validate
        $otherWallet = $organization->primaryWallet($serviceOtherId);
        $smsWallet = $organization->primaryWallet($serviceSmsId);

        if (!$otherWallet || !$smsWallet) {
            throw new \InvalidArgumentException(
                'Organization must have both OTHER and SMS primary wallets'
            );
        }

        $walletService = new WalletAssignmentService();

        // Assign both wallets
        $walletService->assignWallet(
            $otherWallet,
            'workspace',
            $workspace->id
        );

        $walletService->assignWallet(
            $smsWallet,
            'workspace',
            $workspace->id
        );
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/workspaces/{workspaceId}",
     *     summary="Get a specific workspace by its ID",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workspace retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Workspace retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Workspace")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization or Workspace not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization not found")
     *         )
     *     ),
     * )
     */

    public function show(Organization $organization, Workspace $workspace): JsonResponse
    {
        if ($organization->id != $workspace->organization_id) {
            return $this->response(false, 'The workspace does not belong to the specified organization.', null, 404);
        }
        return $this->response(true, 'Workspace retrieved successfully', new \App\Http\Responses\Workspace($workspace));
    }


    /**
     * @OA\Patch(
     *     path="/api/organizations/{organizationId}/workspaces/{workspaceId}",
     *     summary="Update an existing workspace for an organization",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 description="Name of the workspace",
     *                 example="Updated Workspace Name"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 nullable=true,
     *                 description="Description of the workspace",
     *                 example="Updated description of the workspace."
     *             ),
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 description="Status of the workspace (e.g., active, inactive)",
     *                 example="active"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Workspace updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Workspace updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Workspace")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace or Organization not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace or Organization not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {
     *                         "type": "string"
     *                     }
     *                 },
     *                 description="Validation errors"
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, Organization $organization, Workspace $workspace): JsonResponse
    {

        $workspace = $organization->workspaces()->findOrFail($workspace->id);
        if (empty($workspace)) {
            return $this->response(false, 'Workspace not found in the Organization', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Check if at least one field is provided for the update
        if (!$request->filled('name') && !$request->filled('description') && !$request->filled('status')) {
            return $this->response(false, 'At least one field (name, description, or status) must be provided.', null, 400);
        }

        // Only update fields that are provided
        $workspace->update($request->only(['name', 'description', 'status']));

        return $this->response(true, 'Workspace updated successfully', new \App\Http\Responses\Workspace($workspace));
    }

    /**
     * @OA\Delete(
     *     path="/api/organizations/{organizationId}/workspaces/{workspaceId}",
     *     summary="Delete a specific workspace",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=204,
     *         description="Workspace deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace or Organization not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace or Organization not found")
     *         )
     *     )
     * )
     */
    public function destroy(Organization $organization, Workspace $workspace): JsonResponse
    {

        $workspace = $organization->workspaces()->findOrFail($workspace->id);
        if (empty($workspace)) {
            return $this->response(false, 'Workspace not found in the Organization', 404);
        }

        // Delete the workspace
        $workspace->delete();

        return response()->json(null, 204); // No content response for successful delete
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/workspaces/{workspaceId}/agents",
     *     summary="Assign an agent to a workspace",
     *     tags={"Workspaces"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="workspaceId",
     *         in="path",
     *         required=true,
     *         description="ID of the workspace",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Agent assigned successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Agent assigned successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_id", type="string", example="user-uuid"),
     *                 @OA\Property(property="workspace_id", type="string", example="workspace-uuid")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Workspace or Organization not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Workspace or Organization not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={
     *                     "type": "array",
     *                     "items": {
     *                         "type": "string"
     *                     }
     *                 },
     *                 description="Validation errors"
     *             )
     *         )
     *     )
     * )
     */
    public function assignAgent(Request $request, Organization $organization, Workspace $workspace): JsonResponse
    {
        $user = \Auth::user();

        if ($organization->id != $workspace->organization_id) {
            return $this->response(false, 'The workspace does not belong to the specified organization.', null, 404);
        }

        $user->workspace_id = $workspace->id;
        $user->save();

        return $this->response(true, 'Agent assigned successfully', [
            'user_id' => $user->id,
            'workspace_id' => $workspace->id,
        ]);
    }
}
