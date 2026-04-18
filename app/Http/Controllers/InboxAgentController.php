<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Http\Responses\InboxAgent;
use App\Models\IAMRole;
use App\Models\IAMRoleUser;
use App\Models\InboxAgentAvailability;
use App\Models\InboxAgentWorkingHour;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Workspace;
use App\Traits\BillingManager;
use App\Traits\WalletManager;
use Carbon\CarbonTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InboxAgentController extends BaseApiController
{

    use WalletManager, BillingManager;
    public function getInboxAgent(Request $request, Workspace $workspace)
    {
        // Check if User is actually an Inbox Agent

        $authenticatedUser = auth('api')->user();

        $user = User::find($authenticatedUser->getAuthIdentifier());

        if (!$user->isInboxAgent()) {
            return $this->response(false, __('messages.user_not_inbox_agent'), null, 403);

            $fail(__('messages.identifier_required'));
        }

        return $this->response(true, 'Inbox Agent Retrieved Successfully', new InboxAgent($user), 200);
    }

    /**
     * Update the availability and working hours of an inbox agent.
     *
     * @param Request $request
     * @param Workspace $workspace
     * @param User $user
     * @return JsonResponse
     */
    public function update(Request $request, Workspace $workspace, User $user)
    {

        // Check if User is actually an Inbox Agent

        if (!$user->isInboxAgent()) {
            return $this->response(
                false,
                __('messages.user_not_inbox_agent'),
                null,
                403
            );
        }


        $validator = Validator::make($request->all(), [
            'timezone' => [
                'nullable',
                'string',
                Rule::in(CarbonTimeZone::listIdentifiers()), // Ensures it's a valid timezone
            ],
            'availability' => 'nullable|in:active,away,out_of_office',
            'working_hours' => 'nullable|array',
            'working_hours.*.day' => [
                'required',
                'string',
                Rule::in(InboxAgentWorkingHour::WORKDAYS),
            ],
            // Start time & end time must be provided if the day exists
            'working_hours.*.start_time' => [
                'required_with:working_hours.*.day', // If a day exists, start_time must exist
                'date_format:H:i' // Only hours and minutes (HH:mm)
            ],
            'working_hours.*.end_time' => [
                'required_with:working_hours.*.day', // If a day exists, end_time must exist
                'date_format:H:i' // Only hours and minutes (HH:mm)
            ],
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
        }


        // Get validated data
        $validated = $validator->validated();

        // Update availability
        if (isset($validated['timezone']) || isset($validated['availability'])) {
            InboxAgentAvailability::updateOrCreate(
                ['inbox_agent_id' => $user->id],
                [
                    'timezone' => $validated['timezone'] ?? $user->inboxAgentAvailability?->timezone,
                    'availability' => $validated['availability'] ?? $user->inboxAgentAvailability?->availability,
                ]
            );
        }

        // Update working hours
        if (!empty($validated['working_hours'])) {
            foreach ($validated['working_hours'] as $workingHour) {
                InboxAgentWorkingHour::updateOrCreate(
                    ['inbox_agent_id' => $user->id, 'day' => $workingHour['day']],
                    [
                        'start_time' => $workingHour['start_time'] ?? null,
                        'end_time' => $workingHour['end_time'] ?? null,
                    ]
                );
            }
        }

        // Return the updated inbox agent response
        return $this->response(true, "Inbox Agent Updated Successfully", new InboxAgent($user));
    }


    public function getInboxAgents(Request $request, Workspace $workspace)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Get only members that are inbox agents
        $inboxAgents = $workspace->users()
            ->whereHas('IAMRoles', function ($query) {
                $query->where('name', IAMRole::INBOX_AGENT_ROLE);
            })
            ->with([
                'inboxAgentAvailability',
                'inboxAgentWorkingHours'
            ])
            ->paginate($perPage, ['*'], 'page', $page);

        // Transform to InboxAgent format
        $response = $inboxAgents->getCollection()->map(function ($agent) {
            return new InboxAgent($agent);
        });

        $inboxAgents->setCollection($response);

        return $this->paginateResponse(true, 'All Inbox Agents', $inboxAgents);
    }

    public function activateInboxAgent(Request $request, Workspace $workspace, User $user)
    {
        $organization = $workspace->organization;

        $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)->first();

        if (!$extra || !$extra->inbox_agent_quota || $extra->inbox_agent_quota <= 0) {
            return $this->response(false, 'Inbox agent quota not configured.', null, 400);
        }

        // Check billing status
        if ($user->isInboxAgentBillingActive()) {
            return $this->response(true, 'Inbox Agent is already billed and active for this cycle.');
        }

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
        // Billing cycle
        $billingStart = $window['start'];
        $billingEnd = $window['end'];

        $meta = [
            'type' => 'inbox_agent_charge',
            'organization_id' => $organization->id,
            'email' => $user->email,
            'name' => $user->name,
            'frequency' => $extra->frequency,
            'cost' => $cost,
            'billing_cycle_start' => $billingStart->timestamp,
            'billing_cycle_end' => $billingEnd->timestamp,
            'comment' => "Quota charge for inviting inbox agent: {$request->input('email')} ({$extra->frequency})",
            'wallet_id' => $lockedWallet->id
        ];

        $description = "Inbox Agent Charge ({$extra->frequency}) – {$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')} for {$user->email}";
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

        // Update iam_role_user with billing data
        $inboxAgentRoleId = IAMRole::where('name', IAMRole::INBOX_AGENT_ROLE)->value('id');

        if ($inboxAgentRoleId) {
            IAMRoleUser::where('user_id', $user->id)
                ->where('iam_role_id', $inboxAgentRoleId)
                ->where('organization_id', $organization->id)
                ->update([
                    'billing_frequency' => $extra->frequency,
                    'billing_cycle_end' => $billingEnd,
                    'is_billing_active' => true,
                    'wallet_id' => $lockedWallet->id,
                ]);
        }

        return $this->response(true, 'Inbox Agent activated and billed successfully.');
    }
}
