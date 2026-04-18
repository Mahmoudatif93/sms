<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Organization;
use App\Models\OrganizationMembershipPlan;
use App\Models\Service;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Traits\WalletManager;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OrganizationMembershipPlanController extends BaseApiController
{
    use WalletManager;
    /**
     * Get all membership plans for an organization.
     */
    public function index(Request $request, Organization $organization)
    {
        // Fetch paginated membership plans
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $membershipPlans = $organization->membershipPlans()->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(
            success: true,
            message: 'Membership plans retrieved successfully.',
            items: $membershipPlans
        );
    }

    /**
     * Get a specific membership plan for an organization.
     */
    public function show(Organization $organization, $id)
    {
        $membershipPlan = $organization->membershipPlans()->findOrFail($id);

        return $this->response(
            success: true,
            message: 'Membership plan retrieved successfully.',
            data: $membershipPlan
        );
    }

    /**
     * Create a new membership plan for an organization.
     */
    public function store(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'frequency' => 'required|string|in:daily,weekly,monthly,yearly',
            'status' => 'required|string|in:active,inactive',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $validated['organization_id'] = $organization->id;

        $membershipPlan = OrganizationMembershipPlan::create($validated);

        return $this->response(
            success: true,
            message: 'Membership plan created successfully.',
            data: $membershipPlan,
            statusCode: 201
        );
    }

    /**
     * Update an existing membership plan for an organization.
     */
    public function update(Request $request, Organization $organization, $id)
    {
        $membershipPlan = $organization->membershipPlans()->findOrFail($id);

        $validated = $request->validate([
            'price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly',
            'status' => 'nullable|string|in:active,inactive',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $membershipPlan->update($validated);

        return $this->response(
            success: true,
            message: 'Membership plan updated successfully.',
            data: $membershipPlan
        );
    }

    /**
     * Delete a membership plan for an organization.
     */
    public function destroy(Organization $organization, $id)
    {
        $membershipPlan = $organization->membershipPlans()->findOrFail($id);
        $membershipPlan->delete();

        return $this->response(
            success: true,
            message: 'Membership plan deleted successfully.'
        );
    }

    /**
     * Activate a specific membership plan.
     */
    /**
     * Activate a specific membership plan.
     */
    public function activate(Organization $organization, $id)
    {
        $membershipPlan = $organization->membershipPlans()
            ->where('id', '=', $id)->first();


        if (!$membershipPlan) {
            return $this->response(
                success: false,
                message: 'Membership plan not found',
                statusCode: 404
            );
        }


        // Check if the plan is already active
        if ($organization->isMembershipBillingActive($membershipPlan)) {
            return $this->response(
                success: true,
                message: 'The membership plan is already active'
            );
        }

        $cost = $membershipPlan->price;

        $serviceID = Service::firstOrCreate(
            ['name' => \App\Enums\Service::OTHER],
            ['description' => 'whatsapp,hlr']
        )->id;

        $wallet = $organization->wallets()
            ->where('type', '=' , 'primary')
            ->where('service_id', '=', $serviceID)
            ->first();


        if (!$wallet) {
            return $this->response(
                success: false,
                message: "No wallet found for organization with this service",
                statusCode: 400
            );
        }

        $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

        if (!$lockedWallet->hasSufficientFunds($cost)) {
            return $this->response(
                success: false,
                message: "Insufficient wallet balance to activate the membership plan. Required: {$cost} SAR, Available: {$wallet->available_amount} SAR. Please top up and try again.",
                statusCode: 400
            );
        }


        $billingStart = now()->startOfDay();
        $billingEnd = $membershipPlan->frequency === 'yearly'
            ? $billingStart->copy()->addYear()->endOfDay()
            : $billingStart->copy()->addMonth()->endOfDay();


        $meta = [
            'type' => 'membership_plan',
            'organization_id' => $organization->id,
            'membership_plan_id' => $membershipPlan->id,
            'frequency' => $membershipPlan->frequency, // 'monthly' | 'yearly'
            'cost' => $cost,
            'billing_cycle_start' => $billingStart->timestamp,
            'billing_cycle_end' => $billingEnd->timestamp,
            'comment' => "Membership plan charge ({$membershipPlan->frequency}).",
            'wallet_id' => $lockedWallet->id
        ];

        $description = "Membership Plan ({$membershipPlan->frequency}) – "
            . "{$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')}";


        $lockedWallet->amount -= abs($cost);
        $lockedWallet->save();


        $transaction = WalletTransaction::create([
            'wallet_id'         => $lockedWallet->id,
            'amount'            => -abs($cost),
            'transaction_type'  => WalletTransactionType::USAGE,
            'status'            => WalletTransactionStatus::ACTIVE,
            'description'       => $description,
            'meta'              => $meta,
        ]);

        if (!$transaction) {
            return $this->response(false, 'Failed to deduct the amount from wallet.', null, 500);
        }


        $membershipPlan->update(['status' => 'active']);
        $membershipPlan->save();

        return $this->response(true, 'Organization Membership plan activated and billed successfully.');

    }


}
