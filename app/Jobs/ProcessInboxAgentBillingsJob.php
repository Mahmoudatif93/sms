<?php
namespace App\Jobs;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\IAMRole;
use App\Models\IAMRoleUser;
use App\Models\OrganizationWhatsappExtra;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Traits\BillingManager;
use App\Traits\WalletManager;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessInboxAgentBillingsJob implements ShouldQueue
{
    use Queueable, WalletManager, BillingManager;

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

        // Process expired inbox agent subscriptions that need renewal
        $this->processExpiredSubscriptions($currentDate);
    }

    /**
     * Process expired inbox agent subscriptions.
     * Find all inbox agents with expired billing cycles and attempt to renew them.
     * This includes both active (need renewal) and inactive (need reactivation if funds available).
     *
     * Uses chunking for memory efficiency with large datasets.
     */
    protected function processExpiredSubscriptions(Carbon $currentDate): void
    {
        // Get the Inbox Agent role ID
        $inboxAgentRoleId = IAMRole::where('name', IAMRole::INBOX_AGENT_ROLE)
            ->value('id');

        if (!$inboxAgentRoleId) {
            Log::info('ProcessInboxAgentBillingsJob: No Inbox Agent role found');
            return;
        }

        // Find ALL inbox agents that have billing data and their cycle has expired
        // This includes:
        // 1. Active subscriptions that need renewal
        // 2. Inactive subscriptions that might be reactivated if funds are now available
        IAMRoleUser::where('iam_role_id', $inboxAgentRoleId)
            // ->where('organization_id','71caccca-93b9-4820-b5b6-10bf91a67a2a')
            ->whereNotNull('billing_cycle_end')
            ->where('billing_cycle_end', '<', $currentDate)
            ->chunkById(100, function ($subscriptions) use ($currentDate) {
                foreach ($subscriptions as $subscription) {
                    $this->processRenewal($subscription, $currentDate);
                }
            });
    }

    /**
     * Process renewal for a single inbox agent subscription.
     */
    protected function processRenewal(IAMRoleUser $subscription, Carbon $currentDate): void
    {
        try {
            DB::transaction(function () use ($subscription, $currentDate) {
                $organizationId = $subscription->organization_id;
                $frequency = $subscription->billing_frequency ?? 'monthly';
                $walletId = $subscription->wallet_id;

                if (!$organizationId) {
                    Log::warning('ProcessInboxAgentBillingsJob: Missing organization_id', [
                        'subscription_id' => $subscription->id,
                    ]);
                    return;
                }

                // Get organization's WhatsappExtra to get current pricing
                $extra = OrganizationWhatsappExtra::where('organization_id', $organizationId)->first();

                if (!$extra || empty($extra->inbox_agent_quota) || $extra->inbox_agent_quota <= 0) {
                    Log::warning('ProcessInboxAgentBillingsJob: No inbox_agent_quota configured', [
                        'organization_id' => $organizationId,
                    ]);
                    // Deactivate billing - no quota configured
                    $subscription->update(['is_billing_active' => false]);
                    return;
                }

                $cost = $extra->inbox_agent_quota;

                // Get the wallet
                $wallet = Wallet::find($walletId);
                if (!$wallet) {
                    Log::warning('ProcessInboxAgentBillingsJob: Wallet not found', [
                        'wallet_id' => $walletId,
                        'organization_id' => $organizationId,
                    ]);
                    $subscription->update(['is_billing_active' => false]);
                    return;
                }

                // Lock the wallet for update
                $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

                // Calculate new billing window starting from NOW
                $window = $this->computeBillingWindow($frequency);
                $billingStart = $window['start'];
                $billingEnd = $window['end'];

                // Check if wallet has sufficient funds
                if (!$lockedWallet->hasSufficientFunds($cost)) {
                    Log::info('ProcessInboxAgentBillingsJob: Insufficient funds for renewal', [
                        'organization_id' => $organizationId,
                        'user_id' => $subscription->user_id,
                        'required' => $cost,
                        'available' => $lockedWallet->available_amount,
                    ]);
                    // Deactivate billing - insufficient funds
                    $subscription->update(['is_billing_active' => false]);
                    return;
                }

                // Deduct from wallet
                $lockedWallet->amount -= abs($cost);
                $lockedWallet->save();

                // Update subscription with new billing cycle
                $subscription->update([
                    'billing_cycle_end' => $billingEnd,
                    'is_billing_active' => true,
                ]);

                // Get user info for transaction meta
                $user = $subscription->user;
                $email = $user?->email ?? 'unknown';
                $name = $user?->name ?? 'unknown';

                // Create transaction record
                $meta = [
                    'type' => 'inbox_agent_charge',
                    'organization_id' => $organizationId,
                    'email' => $email,
                    'name' => $name,
                    'frequency' => $frequency,
                    'cost' => $cost,
                    'billing_cycle_start' => $billingStart->timestamp,
                    'billing_cycle_end' => $billingEnd->timestamp,
                    'comment' => "Renewal: Inbox Agent Charge ({$frequency}) – {$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')} for {$email}",
                    'wallet_id' => $lockedWallet->id,
                ];

                $description = "Inbox Agent Renewal ({$frequency}) – {$billingStart->format('Y-m-d')} to {$billingEnd->format('Y-m-d')} for {$email}";
                $category = WalletTransaction::WALLET_TRANSACTION_INBOX_AGENT;
                WalletTransaction::create([
                    'wallet_id' => $lockedWallet->id,
                    'amount' => -1 * $cost,
                    'transaction_type' => WalletTransactionType::USAGE,
                    'status' => WalletTransactionStatus::ACTIVE,
                    'description' => $description,
                    'category' => $category,
                    'meta' => $meta,
                ]);

                Log::channel('slack')->info('ProcessInboxAgentBillingsJob: Successfully renewed subscription', [
                    'organization_id' => $organizationId,
                    'user_id' => $subscription->user_id,
                    'cost' => $cost,
                    'billing_end' => $billingEnd->toDateTimeString(),
                ]);
            });
        } catch (\Exception $e) {
            Log::error('ProcessInboxAgentBillingsJob: Failed to process renewal', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
