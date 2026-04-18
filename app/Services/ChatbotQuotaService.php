<?php

namespace App\Services;

use App\Enums\Service as EnumService;
use App\Models\MessageBilling;
use App\Models\OrganizationWhatsappExtra;
use App\Models\WhatsappMessage;
use App\Traits\WalletManager;
use App\Traits\WhatsappWalletManager;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for managing chatbot quota deductions for workflow messages.
 *
 * This service handles the deduction of chatbot quota from the organization's
 * wallet when workflow actions send automated messages.
 */
class ChatbotQuotaService
{
    use WalletManager,WhatsappWalletManager;

    /**
     * Deduct chatbot quota for a workflow message.
     *
     * @param WhatsappMessage $triggerMessage The message that triggered the workflow
     * @return bool Returns true if quota was successfully deducted, false otherwise
     * @throws Exception If there's insufficient balance or quota
     */
    public function deductQuotaForWorkflowMessage(WhatsappMessage $triggerMessage,array $meta = null): bool
    {
        try {
            // Get organization from trigger message
            $organization = $this->getOrganizationFromMessage($triggerMessage);

            if (!$organization) {
                Log::warning('Could not find organization for workflow message', [
                    'message_id' => $triggerMessage->id,
                ]);
                return false;
            }

            // Get organization's WhatsApp extras (quota settings)
            $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)->first();

            if (!$extra || empty($extra->chatbot_quota) || $extra->chatbot_quota <= 0) {
                Log::info('No chatbot quota configured for organization', [
                    'organization_id' => $organization->id,
                ]);
                return true; // No quota configured, allow the message
            }

            // Get the organization's wallet for OTHER service
            $mainWallet = $this->getObjectWallet(
                $organization,
                \App\Models\Service::where('name', EnumService::OTHER)->value('id')
            );

            if (!$mainWallet) {
                throw new Exception('No wallet found for organization. Please contact support.');
            }

            $walletBalance = (float) $mainWallet->amount;
            $chatbotQuota = $extra->chatbot_quota;

            // Check if sufficient balance
            if ($walletBalance - $chatbotQuota < 0) {
                throw new Exception('Insufficient balance for chatbot quota. Please recharge your wallet.');
            }

            // Deduct the quota from wallet
            $chargeSuccess = $this->changeBalanceOther(
                $mainWallet,
                -1 * $chatbotQuota,
                "Chatbot Workflow Message Quota",
                $meta,
                \App\Models\WalletTransaction::WALLET_TRANSACTION_CHATBOT
            );

            if (!$chargeSuccess) {
                throw new Exception('Failed to deduct chatbot quota from wallet.');
            }

            // Create billing record for chatbot quota
            MessageBilling::create([
                'messageable_id' => $triggerMessage->id,
                'messageable_type' => WhatsappMessage::class,
                'type' => MessageBilling::TYPE_CHATBOT,
                'cost' => $chatbotQuota,
                'is_billed' => true,
            ]);

            Log::info('Chatbot quota deducted successfully', [
                'organization_id' => $organization->id,
                'amount' => $chatbotQuota,
                'message_id' => $triggerMessage->id,
                'wallet_balance' => $walletBalance,
                'new_balance' => $walletBalance - $chatbotQuota,
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to deduct chatbot quota', [
                'message_id' => $triggerMessage->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get organization from a WhatsApp message.
     *
     * @param WhatsappMessage $message
     * @return \App\Models\Organization|null
     */
    protected function getOrganizationFromMessage(WhatsappMessage $message): ?\App\Models\Organization
    {
        // Load the channel relationship if not already loaded
        if (!$message->relationLoaded('channel')) {
            $message->load('channel');
        }

        $channel = $message->channel;

        if (!$channel) {
            return null;
        }

        // Get workspace from channel
        $workspace = $channel->workspaces()?->first();

        if (!$workspace) {
            return null;
        }

        return $workspace->organization;
    }

    /**
     * Check if organization has sufficient quota for a workflow message.
     *
     * @param WhatsappMessage $triggerMessage
     * @return array Returns ['success' => bool, 'message' => string, 'quota' => float]
     */
    public function checkQuotaAvailability(WhatsappMessage $triggerMessage): array
    {
        $organization = $this->getOrganizationFromMessage($triggerMessage);

        if (!$organization) {
            return [
                'success' => false,
                'message' => 'Could not find organization for this message',
                'quota' => 0,
            ];
        }

        $extra = OrganizationWhatsappExtra::where('organization_id', $organization->id)->first();

        if (!$extra || empty($extra->chatbot_quota) || $extra->chatbot_quota <= 0) {
            return [
                'success' => true,
                'message' => 'No chatbot quota configured',
                'quota' => 0,
            ];
        }

        $mainWallet = $this->getObjectWallet(
            $organization,
            \App\Models\Service::where('name', EnumService::OTHER)->value('id')
        );

        if (!$mainWallet) {
            return [
                'success' => false,
                'message' => 'No wallet found for organization',
                'quota' => $extra->chatbot_quota,
            ];
        }

        $walletBalance = (float) $mainWallet->amount;
        $chatbotQuota = $extra->chatbot_quota;

        if ($walletBalance - $chatbotQuota < 0) {
            return [
                'success' => false,
                'message' => 'Insufficient balance for chatbot quota',
                'quota' => $chatbotQuota,
                'balance' => $walletBalance,
            ];
        }

        return [
            'success' => true,
            'message' => 'Sufficient quota available',
            'quota' => $chatbotQuota,
            'balance' => $walletBalance,
        ];
    }

    public function prepareWalletTransactionForChatbootAi($workspace){
       return $this->prepareWalletTransactionForChatboot($workspace);
    }

    public function finalizeWhatsappWalletTransactionChatboot($message,$status){
        $this->finalizeWhatsappWalletChatBoot($message,$status);
    }
}
