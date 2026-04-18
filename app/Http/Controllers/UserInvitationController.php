<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Responses\ValidatorErrorResponse;
use App\Mail\UserInvitationMail;
use App\Models\IAMRole;
use App\Models\Organization;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Models\OrganizationUser;
use App\Models\WorkspaceUser;
use App\Models\User;
use App\Traits\BillingManager;
use App\Traits\WalletManager;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mail;
use Str;

class UserInvitationController extends BaseApiController
{

    use WalletManager, BillingManager;
    /**
     * @OA\Post(
     *     path="/api/organizations/{organizationId}/invite",
     *     summary="Invite a new user to the organization",
     *     tags={"Organizations"},
     *     security={{"apiAuth":{}}},
     *     @OA\Parameter(
     *         name="organizationId",
     *         in="path",
     *         required=true,
     *         description="ID of the organization to invite the user to",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="email", type="string", example="test@example.com"),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="roles", type="array", @OA\Items(type="integer", example=1))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Invitation sent successfully."),
     *     @OA\Response(response=400, description="Validation Error(s)."),
     * )
     */
    public function invite(Request $request, $organizationId)
    {
        $organization = Organization::findOrFail($organizationId);

        $validator = Validator::make(
            $request->all(),
            [
                'username' => 'required|string|min:5|max:50|unique:user',
                'email' => 'required|email',
                'name' => 'required|string|max:255',
                'workspaces' => 'required|array|min:1',
                'workspaces.*' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) use ($organizationId) {
                        $workspace = Workspace::where('id', $value)
                            ->where('organization_id', $organizationId)
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
        try {
            return \DB::transaction(function () use ($request, $organization, $organizationId) {
                // Check if the user already exists by email
                $user = User::where('email', $request->input('email'))->first();
                // Check if the user is already a part of the organization
                $existingMembership = OrganizationUser::where('user_id', $user->id ?? null)
                    ->where('organization_id', $organizationId)
                    ->first();
                // If the user is already invited or is already a member, return an error
                if ($existingMembership) {
                    if ($existingMembership->status === 'invited') {
                        return $this->response(false, 'This user has already been invited to the organization.', null, 400);
                    }

                    return $this->response(false, 'This user is already a member of the organization.', null, 400);
                }

                // Check if adding Inbox Agent role and prepare billing data
                $inboxAgentBillingData = null;
                $isInboxAgentRole = IAMRole::whereIn('id', $request->input('roles'))->where('name', '=', 'Inbox Agent')->exists();

                if (!$user && $isInboxAgentRole) {
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
                            'email' => $request->input('email'),
                            'name' => $request->input('name'),
                            'frequency' => $extra->frequency,
                            'cost' => $cost,
                            'billing_cycle_start' => $billingStart->timestamp,
                            'billing_cycle_end' => $billingEnd->timestamp,
                            'comment' => "Quota charge for inviting inbox agent: {$request->input('email')} ({$extra->frequency})",
                            'wallet_id' => $lockedWallet->id
                        ];

                        $description = "Inbox Agent Charge ({$extra->frequency}) – {$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')} for " . $request->input('email');
                        $category = WalletTransaction::WALLET_TRANSACTION_INBOX_AGENT;
                        $lockedWallet->amount -= abs($cost);
                        $lockedWallet->save();

                        $transaction = WalletTransaction::create([
                            'wallet_id' => $lockedWallet->id,
                            'amount' => -1 * $cost,
                            'transaction_type' => WalletTransactionType::USAGE,
                            'status' => WalletTransactionStatus::ACTIVE,
                            'description' => $description,
                            'category' => $category,
                            'meta' => $meta,
                        ]);

                        if (!$transaction) {
                            return $this->response(false, 'Failed to deduct the amount from wallet.', null, 500);
                        }

                        // Store billing data for iam_role_user pivot
                        $inboxAgentBillingData = [
                            'billing_frequency' => $extra->frequency,
                            'billing_cycle_end' => $billingEnd,
                            'is_billing_active' => true,
                            'wallet_id' => $lockedWallet->id,
                        ];
                    }
                }

                if (!$user) {
                    // If user doesn't exist, create them
                    $user = User::create([
                        'username' => $request->input('username'),
                        'name' => $request->input('name'),
                        'email' => $request->input('email'),
                        'password' => null, // They will set a password later
                    ]);
                }

                // Create the invite token
                $inviteToken = Str::random(60);

                // Attach the user to the organization with the 'invited' status
                OrganizationUser::updateOrCreate(
                    ['user_id' => $user->id, 'organization_id' => $organizationId],
                    [
                        'status' => 'invited',
                        'invite_token' => $inviteToken,
                    ]
                );

                $roleIds = (array) $request->input('roles', []);
                $inboxAgentRoleId = IAMRole::where('name', IAMRole::INBOX_AGENT_ROLE)->value('id');

                foreach ($roleIds as $roleId) {
                    $pivotData = ['organization_id' => (string) $organizationId];

                    // Add billing data if this is the Inbox Agent role
                    if ($inboxAgentBillingData && $roleId == $inboxAgentRoleId) {
                        $pivotData = array_merge($pivotData, $inboxAgentBillingData);
                    }

                    $user->IAMRoles()->syncWithoutDetaching([
                        $roleId => $pivotData,
                    ]);
                }

                if ($request->has('workspaces')) {
                    foreach ($request->input('workspaces') as $workspaceData) {
                        WorkspaceUser::create([
                            'user_id' => $user->id,
                            'workspace_id' => $workspaceData,
                            'status' => WorkspaceUser::STATUS_ACTIVE
                        ]);
                    }
                    if ($user->workspace_id == null) {
                        $user->workspace_id = $workspaceData;
                        $user->save();
                    }
                }


                // Create the activation link
                $activationLink = "https://portal.dreams.sa/set-password/?inviteToken={$inviteToken}";

                // Send invitation email
                Mail::to($user->email)->send(new UserInvitationMail($activationLink));
                return $this->response(true, 'Invitation sent successfully.', null, 200);

            });
        } catch (\Exception $e) {
            return $this->response(false, 'Failed to process invitation.', $e->getMessage(), 500);
        }

    }


    /**
     * @OA\Post(
     *     path="/api/organizations/invite/{inviteToken}/accept",
     *     summary="Accept the invitation and set the password",
     *     tags={"Organizations"},
     *     @OA\Parameter(
     *         name="inviteToken",
     *         in="path",
     *         required=true,
     *         description="Invite token sent to the user",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Password set successfully."),
     *     @OA\Response(response=400, description="Invalid invite token."),
     * )
     */
    public function acceptInvite(Request $request, $inviteToken)
    {
        $request->validate([
            'password' => 'required|confirmed|min:8',
        ]);

        $organizationUser = OrganizationUser::whereInviteToken($inviteToken)->first();

        if (!$organizationUser) {
            return $this->response(false, 'Invalid invite token.', null, 400);
        }

        // Get the user and update the password
        $user = User::find($organizationUser->user_id);
        $user->password = Hash::make($request->input('password'));
        $user->save();


        $organizationUser->update(['status' => 'active', 'invite_token' => null]);

        return $this->response(true, 'Password set successfully.', null, 200);
    }


}
