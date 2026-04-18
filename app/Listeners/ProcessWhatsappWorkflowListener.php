<?php

namespace App\Listeners;

use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Enums\Workflow\TriggerType;
use App\Events\WhatsappInteractiveResponseReceived;
use App\Events\WhatsappMessageStatusUpdated;
use App\Events\WhatsappStartConversation;
use App\Models\WhatsappMessage;
use App\Services\Workflow\WhatsappWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Unified listener for processing WhatsApp workflows.
 *
 * Handles both template status updates and interactive message responses.
 */
class ProcessWhatsappWorkflowListener implements ShouldQueue
{
    use InteractsWithQueue;
    public $queue = 'sms-low';
    /**
     * The name of the queue the job should be sent to.
     */

    /**
     * Create the event listener.
     */
    public function __construct(
        protected WhatsappWorkflowService $workflowService,
        protected ChatbotRepositoryInterface $chatbotRepository
    ) {
    }

    /**
     * Handle template message status updates.
     */
    public function handleStatusUpdate(WhatsappMessageStatusUpdated $event): void
    {
        $message = $event->whatsappMessage;
        // if ($this->getRecipientPhoneNumber($message) != "970598704570" || $this->getRecipientPhoneNumber($message) != "+970598704570") {
        //     return;
        // }

        if ($message->type !== WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
            return;
        }

        $latestStatus = $this->getLatestStatus($message);
        if (!$latestStatus) {
            return;
        }

        Log::info('Processing workflow for message status update', [
            'message_id' => $message->id,
            'status' => $latestStatus,
        ]);

        $this->workflowService->processTemplateStatusUpdate($message, $latestStatus);
    }

    /**
     * Handle interactive message responses.
     */
    public function handleInteractiveResponse(WhatsappInteractiveResponseReceived $event): void
    {
        // if ($this->getRecipientPhoneNumber($event->responseMessage) != "970598704570" || $this->getRecipientPhoneNumber($event->responseMessage) != "+970598704570") {
        //     return;
        // }

        if ($event->responseMessage)
            Log::info('Processing interactive workflow for response', [
                'response_message_id' => $event->responseMessage->id,
                'draft_id' => $event->draftId,
                'reply_type' => $event->replyType,
                'reply_id' => $event->replyId,
            ]);

        // Map reply type to TriggerType enum
        $triggerType = $event->replyType === 'button_reply'
            ? TriggerType::BUTTON_REPLY
            : TriggerType::LIST_REPLY;

        $this->workflowService->processInteractiveReply(
            $event->responseMessage,
            $event->draftId,
            $triggerType,
            $event->replyId
        );
    }

    public function handleConversationStarted(WhatsappStartConversation $event): void
    {
        // Skip workflow if chatbot is enabled for this channel/contact
        if ($this->isChatbotEnabled($event->whatsappMessage)) {
            Log::info('Skipping START_CONVERSATION workflow - Chatbot is enabled', [
                'message_id' => $event->whatsappMessage->id,
            ]);
            return;
        }

        Log::info('Processing conversation started', [
            'message_id' => 'started',
        ]);

        $this->workflowService->processConversationStarted($event->whatsappMessage);
    }

    /**
     * Check if chatbot is enabled for this message's channel and contact.
     */
    protected function isChatbotEnabled(WhatsappMessage $message): bool
    {
        // Get channel through the relationship chain:
        // WhatsappMessage -> WhatsappPhoneNumber -> WhatsappConfiguration -> Connector -> Channel
        $whatsappPhoneNumber = $message->whatsappPhoneNumber;

        if (!$whatsappPhoneNumber) {
            Log::warning('isChatbotEnabled: No WhatsappPhoneNumber found', [
                'message_id' => $message->id,
            ]);
            return false;
        }

        $whatsappConfiguration = $whatsappPhoneNumber->whatsappConfiguration;

        if (!$whatsappConfiguration) {
            Log::warning('isChatbotEnabled: No WhatsappConfiguration found', [
                'message_id' => $message->id,
                'phone_number_id' => $whatsappPhoneNumber->id,
            ]);
            return false;
        }

        $connector = $whatsappConfiguration->connector;

        if (!$connector) {
            Log::warning('isChatbotEnabled: No Connector found', [
                'message_id' => $message->id,
                'config_id' => $whatsappConfiguration->id,
            ]);
            return false;
        }

        $channel = $connector->channel;
        $channelId = $channel?->id;

        if (!$channelId) {
            Log::warning('isChatbotEnabled: No Channel found', [
                'message_id' => $message->id,
                'connector_id' => $connector->id,
            ]);
            return false;
        }

        // Get chatbot settings for this channel
        $settings = $this->chatbotRepository->getSettings($channelId);

        if (!$settings || !$settings->is_enabled) {
            Log::debug('isChatbotEnabled: Chatbot not enabled', [
                'channel_id' => $channelId,
                'settings_exists' => (bool) $settings,
                'is_enabled' => $settings?->is_enabled ?? false,
            ]);
            return false;
        }

        // Check whitelist if enabled
        $contactPhone = $this->getRecipientPhoneNumber($message);
        $isAllowed = $settings->isContactAllowed($contactPhone);

        Log::debug('isChatbotEnabled: Contact check', [
            'channel_id' => $channelId,
            'contact_phone' => $contactPhone,
            'whitelist_enabled' => $settings->whitelist_enabled,
            'is_allowed' => $isAllowed,
        ]);

        return $isAllowed;
    }


    /**
     * Get the latest status from the message.
     */
    protected function getLatestStatus(WhatsappMessage $message): ?string
    {
        if (!$message->relationLoaded('statuses')) {
            $message->load('statuses');
        }

        $latestStatus = $message->statuses->sortByDesc('timestamp')->first();

        return $latestStatus?->status;
    }

    /**
     * Determine whether the listener should be queued for status updates.
     */
    public function shouldQueueStatusUpdate(WhatsappMessageStatusUpdated $event): bool
    {
        return $event->whatsappMessage->type === WhatsappMessage::MESSAGE_TYPE_TEMPLATE;
    }

    /**
     * Determine whether the listener should be queued for interactive responses.
     */
    public function shouldQueueInteractiveResponse(WhatsappInteractiveResponseReceived $event): bool
    {
        return true;
    }


    protected function getRecipientPhoneNumber(WhatsappMessage $message): ?string
    {
        if ($message->sender_role === WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS) {
            return $message->recipient?->phone_number ?? $message->recipient?->wa_id;
        }
        return $message->sender?->phone_number ?? $message->sender?->wa_id;
    }
}

