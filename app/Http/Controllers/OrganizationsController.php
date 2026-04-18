<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Responses\Organization;
use App\Http\Responses\OrganizationMember;
use App\Models\IAMRole;
use App\Models\IAMRoleUser;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Models\WorkspaceUser;
use App\Http\Responses\ValidatorErrorResponse;
use App\Traits\BillingManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Str;
use App\Services\UserRegisterService;
use App\Class\payment\ServiceFactory;

/**
 * @OA\Tag(name="Organizations")
 */
class OrganizationsController extends BaseApiController
{
    use BillingManager;

    protected $UserRegisterService;
    public function __construct(UserRegisterService $UserRegisterService)
    {
        $this->UserRegisterService = $UserRegisterService;
    }

    /**
     * @OA\Post(
     *     path="/api/organizations",
     *     summary="Create a new organization",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "countryCode"},
     *             @OA\Property(property="name", type="string", example="My Organization"),
     *             @OA\Property(property="countryCode", type="string", example="US")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Organization created"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'countryCode' => 'required|string|max:2', // ISO3166-1 country code
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }

        $user = Auth::user();

        $organization = \App\Models\Organization::create([
            'id' => Str::uuid(),
            'name' => $request->input('name'),
            'status' => 'active',
            'status_reason' => null,
            'owner_id' => $user->id,
        ]);
        $this->UserRegisterService->createMainWallets($organization);
        $organization->connectToAllPlans();
        $organization->createDefaultMembershipPlan();
        $organization->createDefaultWhatsappSetting();
        $organization->createDefaultWhatsappExtra();

        $organizationResponse = new Organization($organization);


        return $this->response(true, 'Organization created!', $organizationResponse, 201);
    }


    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}",
     *     summary="Get an organization by ID",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         description="The organization ID"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization found",
     *         @OA\JsonContent(ref="#/components/schemas/Organization")
     *     )
     * )
     */
    public function show(\App\Models\Organization $organization): JsonResponse
    {
        return $this->response(true, 'Organization found!', new Organization($organization));
    }

    /**
     * @OA\Patch(
     *     path="/api/organizations/{organizationId}",
     *     summary="Update an organization's details",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         description="The organization ID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Organization Name"),
     *             @OA\Property(property="avatarUrl", type="string", example="https://example.com/avatar.jpg"),
     *             @OA\Property(property="status", type="string", enum={"active", "banned"}, example="active"),
     *             @OA\Property(property="statusReason", type="string", example="Violation of terms")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Organization updated",
     *         @OA\JsonContent(ref="#/components/schemas/Organization")
     *     )
     * )
     */
    public function update(Request $request, \App\Models\Organization $organization): JsonResponse
    {

        // Validate the incoming request
        $validator = Validator::make(
            $request->all(),
            [
                'status' => 'nullable|string|in:governmental,charitable,private',
                'commercial_registration_number' => 'nullable|string|max:255',
                'unified_number' => 'nullable|string',
                'file_commercial_register' => 'nullable|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
                'file_value_added_tax_certificate' => 'nullable|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Check if either 'name' or 'avatarUrl' is present in the request payload
        if (!$request->has('status') && !$request->has('commercial_registration_number') && !$request->has('unified_number')) {
            return $this->response(false, 'Either status or commercial registration number or unified number  must be provided', [], 400);
        }


        // Update organization's name if provided
        // if ($request->filled('name')) {
        //     $organization->name = $request->input('name');
        // }
        if ($request->filled('status')) {
            $organization->type = $request->input('status');
        }
        if ($request->filled('commercial_registration_number')) {
            $organization->commercial_registration_number = $request->input('commercial_registration_number');
        }
        if ($request->filled('unified_number')) {
            $organization->unified_number = $request->input('unified_number');
        }

        // Handle avatar URL
        if ($request->has('avatarUrl')) {
            if (is_null($request->input('avatarUrl'))) {
                // If avatarUrl is null, clear the avatar (delete the current one)
                $organization->clearMediaCollection('organization-avatar');
            } else {
                try {
                    // Clear any existing avatar before uploading a new one
                    $organization->clearMediaCollection('organization-avatar');

                    // Here we assume the `avatarUrl` is a valid file from the request
                    if ($request->hasFile('file')) {
                        $organization->addMediaFromRequest('file')->toMediaCollection('organization-avatar', 'oss');
                    }

                } catch (\Exception $e) {
                    return $this->response(false, 'Avatar Upload Failed: ' . $e->getMessage(), [], 500);
                }
            }
        }

        if ($request->has('file_commercial_register') && $request->hasFile('file_commercial_register')) {
            $organization->clearMediaCollection('organization-commercial-register');
            $organization->addMediaFromRequest('file_commercial_register')->toMediaCollection('organization-commercial-register', 'oss');
        }

        if ($request->has('file_value_added_tax_certificate') && $request->hasFile('file_value_added_tax_certificate')) {
            $organization->clearMediaCollection('organization-value-added-tax-certificate');
            $organization->addMediaFromRequest('file_value_added_tax_certificate')->toMediaCollection('organization-value-added-tax-certificate', 'oss');
        }

        // Save the changes if any were made
        $organization->save();

        // Return the updated organization details
        return $this->response(true, 'Organization updated successfully!', new Organization($organization));
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/upload-avatar",
     *     summary="Upload media for an organization",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid"),
     *         description="Organization UUID"
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="file",
     *                     description="File to be uploaded",
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="File uploaded successfully"
     *     )
     * )
     */
    public function uploadAvatar(Request $request, \App\Models\Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,gif|max:2048', // 2048KB = 2MB
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Step 2: Upload the file using Spatie Media Library and Alibaba OSS
        try {

            // First, clear the existing media in the 'organization-avatar' collection
            $organization->clearMediaCollection('organization-avatar');

            // Upload file to media collection (OSS disk configured in Spatie)
            $organization
                ->addMediaFromRequest('file')
                ->toMediaCollection('organization-avatar', 'oss'); // 'oss' refers to the configured Alibaba OSS disk

        } catch (\Exception $e) {
            return $this->response(false, 'File Upload Failed: ' . $e->getMessage(), [], 500);
        }

        // Return the signed URL to the frontend
        return $this->response(true, "Media Uploaded Successfully");

    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organizationId}/members",
     *     summary="Get all members of an organization with pagination",
     *     tags={"Organizations"},
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
     *         description="Number of members per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of organization members",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All Organization Members"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/OrganizationMember")
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
    public function getMembers(Request $request, \App\Models\Organization $organization)
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Paginate the members
        $members = $organization->members()
            ->wherePivotNull('organization_user.deleted_at')
            ->with([
                'IAMRoles' => function ($query) use ($organization) {
                    $query->wherePivot('organization_id', $organization->id);
                },
                'workspaces' => function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                }
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform members into OrganizationMember response format
        $response = $members->getCollection()->map(function ($member) use ($organization) {
            return new OrganizationMember($member, $organization->id);
        });

        // Replace the collection with the transformed data
        $members->setCollection($response);

        return $this->paginateResponse(true, 'All Organization Members', $members);
    }


    /**
     * @OA\Delete(
     *     path="/api/organizations/{organizationId}/members/{memberId}",
     *     summary="Delete a member from an organization",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         required=true,
     *         description="ID of the member to be deleted",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Member deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Member deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Organization or Member not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Organization or Member not found")
     *         )
     *     )
     * )
     */
    public function deleteMember(\App\Models\Organization $organization, $memberId)
    {

        try {
            // Check if the member exists in the organization
            $member = $organization->members()
                ->wherePivotNull('organization_user.deleted_at')->find($memberId);


            if (!$member) {
                return $this->response(false, 'Member not found', null, 404);
            }

            // Debug: check what you're about to delete
            \Log::info('Deleting member', [
                'member_id' => $memberId,
                'organization_id' => $organization->id,
            ]);

            // 1) Delete from organization_user
            $deleted = \App\Models\OrganizationUser::where('user_id', $memberId)
                ->where('organization_id', $organization->id)
                ->delete();

            \Log::info('Deleted org_user rows', ['count' => $deleted]);

            // 2) Detach ONLY roles for this org
            $rolesDetached = $member->IAMRoles()
                ->wherePivot('organization_id', (string) $organization->id)
                ->detach();

            \Log::info('Detached IAM roles', ['count' => $rolesDetached]);

            // 3) Detach workspaces in this org
            $workspaceIdsInThisOrg = \App\Models\Workspace::where('organization_id', $organization->id)
                ->pluck('id');

            if ($workspaceIdsInThisOrg->isNotEmpty()) {
                $workspacesDetached = $member->workspaces()
                    ->whereIn('workspace_id', $workspaceIdsInThisOrg)
                    ->detach();

                \Log::info('Detached workspaces', ['count' => $workspacesDetached]);
            }

            return $this->response(true, 'Member deleted successfully', null, 200);

        } catch (\Throwable $e) {
            \Log::error('Failed to delete member', [
                'member_id' => $memberId,
                'organization_id' => $organization->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->response(false, 'Error deleting member: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/organizations/{organizationId}/members/{memberId}",
     *     summary="Update a member's roles and workspaces in an organization",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         required=true,
     *         description="ID of the member to be updated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"workspaces", "roles"},
     *             @OA\Property(
     *                 property="workspaces",
     *                 type="array",
     *                 description="Array of workspace IDs",
     *                 @OA\Items(type="string", format="uuid")
     *             ),
     *             @OA\Property(
     *                 property="roles",
     *                 type="array",
     *                 description="Array of IAM role IDs",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Member updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Member update"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Member not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Member not found")
     *         )
     *     )
     * )
     */
    public function updateMember(Request $request, \App\Models\Organization $organization, $memberId)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'workspaces' => 'required|array|min:1',
                'workspaces.*' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($organization) {
                        $workspace = Workspace::where('id', $value)
                            ->where('organization_id', $organization->id)
                            ->first();

                        if (!$workspace) {
                            $fail('The selected workspace is not valid for this organization.');
                        }
                    }
                ],
                'roles' => 'required|array|min:1',
                'roles.*' => 'exists:iam_roles,id'
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }
        // Check if the member exists in the organization
        $member = $organization->members()
            ->wherePivotNull('organization_user.deleted_at')->find($memberId);

        if (!$member) {
            return $this->response(false, 'Member not found', null, 404);
        }

        $roleIds = (array) $request->input('roles', []);

        // Check if adding Inbox Agent role
        $inboxAgentRoleId = IAMRole::where('name', IAMRole::INBOX_AGENT_ROLE)->value('id');
        $isAddingInboxAgent = in_array($inboxAgentRoleId, $roleIds);

        // First, remove existing roles and workspaces only for this organization
        $member->workspaces()->detach(
            Workspace::where('organization_id', $organization->id)->pluck('id')
        );

        // Soft delete IAM roles instead of detach (to preserve billing info)
        $roleIdsToRemove = IAMRole::where('organization_id', $organization->id)
            ->orWhereNull('organization_id')
            ->pluck('id');

        IAMRoleUser::where('user_id', $member->id)
            ->whereIn('iam_role_id', $roleIdsToRemove)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => now()]);

        // Then attach the new ones
        $member->workspaces()->attach($request->input('workspaces'));
        $inboxAgentBillingData = null;

        // If adding Inbox Agent role, check if billing is needed
        // Check for active billing in ALL records (including soft deleted) to avoid double charging
        $hasActiveBilling = IAMRoleUser::where('user_id', $member->id)
            ->where('iam_role_id', $inboxAgentRoleId)
            ->where('organization_id', $organization->id)
            ->where('is_billing_active', true)
            ->where('billing_cycle_end', '>=', now())
            ->withTrashed()
            ->exists();

        if ($isAddingInboxAgent && !$hasActiveBilling) {
            $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)->first();

            if (!empty($extra->inbox_agent_quota) && $extra->inbox_agent_quota > 0) {
                $cost = $extra->inbox_agent_quota;
                $wallet = $organization->getMainOtherWallet();

                if (!$wallet) {
                    return $this->response(false, 'No wallet found for this organization.', null, 400);
                }

                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                if (!$lockedWallet->hasSufficientFunds($cost)) {
                    return $this->response(
                        false,
                        "Insufficient balance to add an Inbox Agent. Required: {$extra->inbox_agent_quota} SAR, Available: {$wallet->available_amount} SAR.",
                        null,
                        402
                    );
                }

                $window = $this->computeBillingWindow($extra->frequency);
                $billingStart = $window['start'];
                $billingEnd = $window['end'];

                $meta = [
                    'type' => 'inbox_agent_charge',
                    'organization_id' => $organization->id,
                    'email' => $member->email,
                    'name' => $member->name,
                    'frequency' => $extra->frequency,
                    'cost' => $cost,
                    'billing_cycle_start' => $billingStart->timestamp,
                    'billing_cycle_end' => $billingEnd->timestamp,
                    'comment' => "Inbox Agent role added via updateMember for {$member->email} ({$extra->frequency})",
                    'wallet_id' => $lockedWallet->id
                ];

                $description = "Inbox Agent Charge ({$extra->frequency}) – {$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')} for {$member->email}";
                $category = WalletTransaction::WALLET_TRANSACTION_INBOX_AGENT;
                $lockedWallet->amount -= abs($cost);
                $lockedWallet->save();

                WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'amount' => -1 * $cost,
                    'transaction_type' => WalletTransactionType::USAGE,
                    'status' => WalletTransactionStatus::ACTIVE,
                    'description' => $description,
                    'category' => $category,
                    'meta' => $meta,
                ]);

                $inboxAgentBillingData = [
                    'billing_frequency' => $extra->frequency,
                    'billing_cycle_end' => $billingEnd,
                    'is_billing_active' => true,
                    'wallet_id' => $lockedWallet->id,
                ];
            }
        }

        foreach ($roleIds as $roleId) {
            $pivotData = ['organization_id' => (string) $organization->id];

            // Add billing data if this is the Inbox Agent role
            if ($inboxAgentBillingData && $roleId == $inboxAgentRoleId) {
                $pivotData = array_merge($pivotData, $inboxAgentBillingData);
            }

            // Check if a soft-deleted record exists and restore it
            $existingPivot = IAMRoleUser::where('user_id', $member->id)
                ->where('iam_role_id', $roleId)
                ->where('organization_id', $organization->id)
                ->where('is_billing_active', true)
                ->where('billing_cycle_end', '>=', now())
                ->withTrashed()
                ->latest('created_at')->first();

            if ($existingPivot) {
                // Restore and update the existing record
                $existingPivot->restore();
            } else {
                // Create new record
                $member->IAMRoles()->attach($roleId, $pivotData);
            }
        }


        return $this->response(false, 'Member update', $member, 200);

    }

    public function getMember(\App\Models\Organization $organization, $memberId)
    {

        // Check if the member exists in the organization
        $member = $organization->members()
            ->wherePivotNull('organization_user.deleted_at')->find($memberId);

        if (!$member) {
            return $this->response(false, 'Member not found', null, 404);
        }

        // // Detach the member from the organization
        // $organization->members()->detach($memberId);
        // $organization->IAMRoles()->detach($memberId);
        // $organization->workspaces()->detach($memberId);



        return $this->response(true, 'Member Data', $member, 200);
    }
    public function index(Request $request): JsonResponse
    {
        // Get query parameters for filtering
        $name = $request->query('name');
        $status = $request->query('status');
        $ownerName = $request->query('owner_name');

        // Build the query with relationships
        $query = \App\Models\Organization::with('owner');

        if (!is_null($name)) {
            $query->where('name', 'LIKE', "%$name%");
        }

        if (!is_null($status)) {
            $query->where('status', $status);
        }

        if (!is_null($ownerName)) {
            $query->whereHas('owner', function ($q) use ($ownerName) {
                $q->where('name', 'LIKE', "%$ownerName%");
            });
        }

        // Pagination parameters
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        // Fetch paginated organizations
        $organizations = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform organizations into the response format
        $response = $organizations->getCollection()->map(function ($organization) {
            return new Organization($organization);
        });

        // Replace the collection with the transformed data
        $organizations->setCollection($response);

        return $this->paginateResponse(true, 'Organizations retrieved successfully.', $organizations);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/organizations/{organization}/charge",
     *     summary="Charge an organization's wallet",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "service"},
     *             @OA\Property(
     *                 property="amount",
     *                 type="integer",
     *                 example=1000,
     *                 description="Amount to charge"
     *             ),
     *             @OA\Property(
     *                 property="service",
     *                 type="string",
     *                 enum={"sms", "other"},
     *                 example="sms",
     *                 description="Service type"
     *             ),
     *             @OA\Property(
     *                 property="sms_point",
     *                 type="integer",
     *                 example=100,
     *                 description="Required when service is 'sms'. Number of SMS points"
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 example="Monthly SMS package",
     *                 description="Optional reason for the charge"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Charge processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Charge processed successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/ValidatorErrorResponse"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error processing charge",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error processing charge: error details"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function charge(Request $request, \App\Models\Organization $organization): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'service' => 'required|in:sms,other',
            'sms_point' => 'required_if:service,sms|integer',
            'reason' => 'sometimes|string|max:255'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        try {
            $service = ServiceFactory::getService($request->service);
            $sms_point = $request->sms_point ?? 0;
            $sms_price = $request->amount > 0 ? ($sms_point / $request->amount) : 0;
            $service->ChangeWalletV2($organization, $request->amount, $request->sms_point ?? 0, $sms_price, );
            return $this->response(true, 'Charge processed successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error processing charge: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organizationId}/members/{memberId}/activate",
     *     summary="Activate an invited member and update their profile",
     *     tags={"Organizations"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="memberId",
     *         in="path",
     *         required=true,
     *         description="ID of the member to be activated",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         description="Required only if user profile is incomplete (no password set)",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", minLength=4, maxLength=50, example="John Doe", description="Required if user has no name"),
     *             @OA\Property(property="number", type="string", pattern="^[\+]?[0-9\s\-\(\)]+$", example="+966501234567", description="Required if user has no number"),
     *             @OA\Property(property="password", type="string", minLength=8, example="password123", description="Required if user has no password"),
     *             @OA\Property(property="password_confirmation", type="string", minLength=8, example="password123", description="Required if password is provided"),
     *             @OA\Property(property="country_id", type="integer", example=1, description="Required if user has no country"),
     *             @OA\Property(property="phone", type="string", pattern="^[\+]?[0-9\s\-\(\)]+$", example="+966501234567", description="Required if user has no phone"),
     *             @OA\Property(property="address", type="string", example="123 Main Street, Riyadh", description="Required if user has no address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Member activated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Member activated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/OrganizationMember")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error or member not invited",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Member is not in invited status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Member not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Member not found")
     *         )
     *     )
     * )
     */
    public function activateMember(Request $request, \App\Models\Organization $organization, $memberId): JsonResponse
    {
        try {
            // Find the member in the organization
            $member = $organization->members()
                ->wherePivotNull('organization_user.deleted_at')
                ->find($memberId);

            if (!$member) {
                return $this->response(false, 'Member not found', null, 404);
            }

            // Check if the member is in invited status
            $organizationUser = \App\Models\OrganizationUser::where('user_id', $memberId)
                ->where('organization_id', $organization->id)
                ->first();

            if (!$organizationUser || $organizationUser->status !== 'invited') {
                return $this->response(false, 'Member is not in invited status', null, 400);
            }

            // Check if user has complete profile (password exists)
            $hasCompleteProfile = !is_null($member->password) &&
                !is_null($member->name) &&
                !is_null($member->phone);

            if (!$hasCompleteProfile) {
                // User needs to complete profile - validate required fields
                $validator = Validator::make($request->all(), [
                    'name' => 'required|string|min:4|max:50',
                    'number' => 'required|string|regex:/^[\+]?[0-9\s\-\(\)]+$/',
                    'password' => 'required|string|min:8|confirmed',
                    'country_id' => 'required|exists:country,id',
                    'phone' => 'required|string|regex:/^[\+]?[0-9\s\-\(\)]+$/',
                    'address' => 'required|string',
                ]);

                if ($validator->fails()) {
                    return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
                }

                // Update user profile with the provided data
                $member->update([
                    'name' => $request->input('name'),
                    'number' => str_replace(' ', '', $request->input('number')),
                    'password' => Hash::make($request->input('password')),
                    'country_id' => $request->input('country_id'),
                    'phone' => str_replace(' ', '', $request->input('phone')),
                    'address' => $request->input('address'),
                    'workspace_id' => WorkspaceUser::getFirstWorkspaceId($member->id)
                ]);
            }

            // Activate the member by updating the pivot table status
            $organizationUser->update([
                'status' => 'active',
                'invite_token' => null // Clear the invite token
            ]);

            // Load the updated member with relationships for response
            $member = $organization->members()
                ->wherePivotNull('organization_user.deleted_at')
                ->with([
                    'IAMRoles' => function ($query) use ($organization) {
                        $query->wherePivot('organization_id', $organization->id);
                    },
                    'workspaces' => function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id);
                    }
                ])
                ->find($memberId);

            return $this->response(true, 'Member activated successfully', new OrganizationMember($member, $organization->id), 200);

        } catch (\Exception $e) {
            return $this->response(false, 'Error activating member: ' . $e->getMessage(), null, 500);
        }
    }
}
