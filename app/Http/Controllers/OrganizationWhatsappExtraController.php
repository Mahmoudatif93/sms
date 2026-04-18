<?php

namespace App\Http\Controllers;

use App\Enums\WalletTransactionStatus;
use App\Models\Organization;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Models\WalletTransaction;
use App\Traits\WalletManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Validator;

class OrganizationWhatsappExtraController extends BaseApiController
{

    use WalletManager;
    /**
     * Display a listing of the organization WhatsApp extras.
     *
     * @param Request $request
     * @param Organization $organization
     * @return JsonResponse
     */
    public function index(Request $request, Organization $organization)
    {
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Query organization-specific extras
        $extras = OrganizationWhatsappExtra::where('organization_id', $organization->id)
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(
            true,
            'Organization WhatsApp extras retrieved successfully.',
            $extras
        );
    }

    /**
     * Show the details of a specific WhatsApp extra.
     *
     * @param Organization $organization
     * @param int $id
     * @return JsonResponse
     */
    public function show(Organization $organization, $id)
    {
        $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)
            ->where('id', $id)
            ->first();

        if (!$extra) {
            return $this->response(false, 'Organization WhatsApp extra not found.', null, 404);
        }

        return $this->response(true, 'Organization WhatsApp extra retrieved successfully.', $extra);
    }

    /**
     * Store a newly created WhatsApp extra.
     *
     * @param Request $request
     * @param Organization $organization
     * @return JsonResponse
     */
    public function store(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'translation_quota' => 'required|numeric|min:0',
            'chatbot_quota' => 'required|numeric|min:0',
            'hosting_quota' => 'required|numeric|min:0',
            'inbox_agent_quota' => 'required|numeric|min:0',
            'frequency' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'is_active' => 'boolean',
            'free_tier' => 'boolean',
            'free_tier_limit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 422);
        }

        $data = $request->all();
        $data['organization_id'] = $organization->id;

        $extra = OrganizationWhatsappExtra::create($data);

        return $this->response(true, 'Organization WhatsApp extra created successfully.', $extra, 201);
    }

    /**
     * Update an existing WhatsApp extra.
     *
     * @param Request $request
     * @param Organization $organization
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, Organization $organization, $id)
    {
        $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)
            ->where('id', $id)
            ->first();

        if (!$extra) {
            return $this->response(false, 'Organization WhatsApp extra not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'translation_quota' => 'nullable|numeric|min:0',
            'chatbot_quota' => 'nullable|numeric|min:0',
            'hosting_quota' => 'nullable|numeric|min:0',
            'inbox_agent_quota' => 'nullable|numeric|min:0',
            'frequency' => 'nullable|string',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'is_active' => 'boolean',
            'free_tier' => 'boolean',
            'free_tier_limit' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 422);
        }

        $extra->update($request->all());

        return $this->response(true, 'Organization WhatsApp extra updated successfully.', $extra);
    }

    /**
     * Remove an existing WhatsApp extra.
     *
     * @param Organization $organization
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Organization $organization, $id)
    {
        $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)
            ->where('id', $id)
            ->first();

        if (!$extra) {
            return $this->response(false, 'Organization WhatsApp extra not found.', null, 404);
        }

        $extra->delete();

        return $this->response(true, 'Organization WhatsApp extra deleted successfully.');
    }

    public function activate(Organization $organization, $id)
    {
        // Find the hosting plan for the organization
        $hostingPlan = $organization->hostingPlans()->find($id);

        if (!$hostingPlan) {
            return $this->response(
                success: false,
                message: 'Hosting plan not found',
                statusCode: 404
            );
        }

        // Check if the plan is already active
        if ($hostingPlan->is_active) {
            return $this->response(
                success: true,
                message: 'The hosting plan is already active'
            );
        }

        $currentDate = now();

        // Retrieve organization wallet
        $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));
        $price = $hostingPlan->hosting_quota;

        // Calculate the current billing cycle
        $effectiveDate = Carbon::createFromTimestamp($hostingPlan->effective_date);
        $cyclesElapsed = $hostingPlan->frequency === 'monthly'
            ? $effectiveDate->diffInMonths($currentDate)
            : $effectiveDate->diffInYears($currentDate);

        $billingCycleStartDate = $hostingPlan->frequency === 'monthly'
            ? $effectiveDate->addMonths($cyclesElapsed)
            : $effectiveDate->addYears($cyclesElapsed);

        $billingCycleEndDate = $hostingPlan->frequency === 'monthly'
            ? $billingCycleStartDate->copy()->addMonth()
            : $billingCycleStartDate->copy()->addYear();

        // Check if a payment log exists for the current billing cycle
        $lastPayment = WalletTransaction::where('wallet_id', $mainWallet->id)
            ->where('description', 'Hosting Plan Charge')
            ->whereBetween('created_at', [$billingCycleStartDate, $billingCycleEndDate])
            ->where('amount', -1 * $price)
            ->where('status', WalletTransactionStatus::ACTIVE)
            ->first();

        if (!$lastPayment) {
            // Try to charge the wallet
            $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $price, 'Hosting Plan Charge',null,WalletTransaction::WALLET_TRANSACTION_HOSTING);
            if ($chargeSuccess) {
                // Mark the plan as active
                $hostingPlan->update(['is_active' => true]);

                return $this->response(
                    success: true,
                    message: 'Hosting plan activated successfully',
                    data: $hostingPlan
                );
            } else {
                return $this->response(
                    success: false,
                    message: 'Insufficient wallet balance to activate the hosting plan',
                    statusCode: 400
                );
            }
        }

        // If a valid payment log exists, simply activate the plan
        $hostingPlan->update(['is_active' => true]);

        return $this->response(
            success: true,
            message: 'Hosting plan activated successfully',
            data: $hostingPlan
        );
    }



}
