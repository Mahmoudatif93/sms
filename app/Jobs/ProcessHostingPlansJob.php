<?php

namespace App\Jobs;

use App\Enums\WalletTransactionStatus;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Service;
use App\Models\WalletTransaction;
use App\Traits\WalletManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessHostingPlansJob implements ShouldQueue
{
    use Queueable, WalletManager;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $currentDate = now();

        // Process active hosting plans
        $this->processActiveHostingPlans($currentDate);

        // Process inactive hosting plans
        $this->processInactiveHostingPlans($currentDate);
    }

    /**
     * Process active hosting plans.
     */
    protected function processActiveHostingPlans($currentDate): void
    {
        $activeHostingPlans = OrganizationWhatsappExtra::where('is_active', '=', true)->orderBy('id','desc')->get();

        foreach ($activeHostingPlans as $plan) {
            $organization = $plan->organization;
            $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));
            $price = $plan->hosting_quota;

            // Calculate the current billing cycle
            $effectiveDate = \Carbon\Carbon::parse($plan->effective_date);
            $cyclesElapsed = $plan->frequency === 'monthly'
                ? $effectiveDate->diffInMonths($currentDate)
                : $effectiveDate->diffInYears($currentDate);
         
            $billingCycleStartDate = $plan->frequency === 'monthly'
                ? $effectiveDate->copy()->addMonths($cyclesElapsed)
                : $effectiveDate->copy()->addYears($cyclesElapsed);

            $billingCycleEndDate = $plan->frequency === 'monthly'
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
                $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $price, 'Hosting Plan Charge',null,'hosting');
                if ($chargeSuccess) {
                    // Keep the plan active if the wallet charge succeeds
                    $plan->update(['is_active' => true]);
                } else {
                    // Deactivate the plan if wallet charge fails
                    $plan->update(['is_active' => false]);
                }
            }
        }
    }

    /**
     * Process inactive hosting plans.
     */
    protected function processInactiveHostingPlans($currentDate): void
    {
        $inactiveHostingPlans = OrganizationWhatsappExtra::where('is_active', '=', false)->get();

        foreach ($inactiveHostingPlans as $plan) {
            $organization = $plan->organization;
            $mainWallet = $this->getObjectWallet($organization, Service::where('name', \App\Enums\Service::OTHER)->value('id'));
            $price = $plan->hosting_quota;

            // Calculate the current billing cycle
            $effectiveDate = \Carbon\Carbon::parse($plan->effective_date);
            $cyclesElapsed = $plan->frequency === 'monthly'
                ? $effectiveDate->diffInMonths($currentDate)
                : $effectiveDate->diffInYears($currentDate);

            $billingCycleStartDate = $plan->frequency === 'monthly'
                ? $effectiveDate->copy()->addMonths($cyclesElapsed)
                : $effectiveDate->copy()->addYears($cyclesElapsed);

            $billingCycleEndDate = $plan->frequency === 'monthly'
                ? $billingCycleStartDate->copy()->addMonth()
                : $billingCycleStartDate->copy()->addYear();

            // Attempt to charge the wallet
            $chargeSuccess = $this->changeBalanceOther($mainWallet, -1 * $price, 'Hosting Plan Charge');

            if ($chargeSuccess) {
                // Reactivate the plan if the wallet charge succeeds
                $plan->update(['is_active' => true]);
            }
        }
    }
}
