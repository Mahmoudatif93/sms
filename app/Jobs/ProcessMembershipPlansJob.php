<?php

namespace App\Jobs;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\OrganizationMembershipPlan;
use App\Models\Service;
use App\Models\WalletTransaction;
use App\Traits\WalletManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessMembershipPlansJob implements ShouldQueue
{
    use Queueable, WalletManager;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $currentDate = now();

        // Process active plans
        $this->processActivePlans($currentDate);

        // Process inactive plans
        $this->processInactivePlans($currentDate);
    }

    /**
     * Process active membership plans.
     */
    protected function processActivePlans($currentDate): void
    {
        $activeMembershipPlans = OrganizationMembershipPlan::where('status', '=', 'active')->get();

        foreach ($activeMembershipPlans as $plan) {
            $organization = $plan->organization;
            $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));
            $price = $plan->price;

            // Calculate the current billing cycle
            $startDate = \Illuminate\Support\Carbon::parse($plan->start_date);
            $cyclesElapsed = $plan->isMonthly()
                ? $startDate->diffInMonths($currentDate)
                : $startDate->diffInYears($currentDate);

            $billingCycleStartDate = $plan->isMonthly()
                ? $startDate->copy()->addMonths($cyclesElapsed)
                : $startDate->copy()->addYears($cyclesElapsed);

            $billingCycleEndDate = $plan->isMonthly()
                ? $billingCycleStartDate->copy()->addMonth()
                : $billingCycleStartDate->copy()->addYear();

            // Check if a payment log exists for the current billing cycle
            $lastPayment = WalletTransaction::where('wallet_id', $mainWallet->id)
                ->where('description', 'Membership Plan Charge')
                ->whereBetween('created_at', [$billingCycleStartDate, $billingCycleEndDate])
                ->where('amount', -1 * $price)
                ->where('status', WalletTransactionStatus::ACTIVE)
                ->first();

            if (!$lastPayment) {
                // Try to charge the wallet
                $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $price, 'Membership Plan Charge');
                if ($chargeSuccess) {
                    // Keep the plan active if the wallet charge succeeds
                    $plan->update(['status' => 'active']);
                } else {
                    // Deactivate the plan if wallet charge fails
                    $plan->update(['status' => 'inactive']);
                }
            }
        }
    }

    /**
     * Process inactive membership plans.
     */
    protected function processInactivePlans($currentDate): void
    {
        $inactiveMembershipPlans = OrganizationMembershipPlan::where('status', '=', 'inactive')->get();

        foreach ($inactiveMembershipPlans as $plan) {
            $organization = $plan->organization;
            $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));
            $price = $plan->price;

            // Calculate the current billing cycle
            $startDate = \Illuminate\Support\Carbon::parse($plan->start_date);
            $cyclesElapsed = $plan->isMonthly()
                ? $startDate->diffInMonths($currentDate)
                : $startDate->diffInYears($currentDate);

            $billingCycleStartDate = $plan->isMonthly()
                ? $startDate->copy()->addMonths($cyclesElapsed)
                : $startDate->copy()->addYears($cyclesElapsed);

            $billingCycleEndDate = $plan->isMonthly()
                ? $billingCycleStartDate->copy()->addMonth()
                : $billingCycleStartDate->copy()->addYear();

            // Attempt to charge the wallet
            $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $price, 'Membership Plan Charge');

            if ($chargeSuccess) {
                // Reactivate the plan if the wallet charge succeeds
                $plan->update(['status' => 'active']);
            }
        }
    }
}