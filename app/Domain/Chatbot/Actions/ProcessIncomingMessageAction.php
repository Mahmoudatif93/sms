<?php

namespace App\Domain\Chatbot\Actions;

use App\Domain\Chatbot\DTOs\ChatbotResponseDTO;
use App\Domain\Chatbot\Services\ChatbotService;
use App\Domain\Conversation\Services\ConversationService;
use App\Models\Conversation;

class ProcessIncomingMessageAction
{
    public function __construct(
        private ChatbotService $chatbotService,
        private SendBotResponseAction $sendResponseAction,
        private ConversationService $conversationService,
    ) {}

    public function execute(Conversation $conversation, string $message): ?ChatbotResponseDTO
    {
        // Check if chatbot should process this message
        if (!$this->chatbotService->shouldProcess($conversation)) {
            return null;
        }

        // Process the message (AI will determine if customer requested handoff)
        $response = $this->chatbotService->processMessage($conversation, $message);

        // If bot is disabled, return null
        if ($response->disabled) {
            return null;
        }

        // Handle handoff FIRST if AI detected customer explicitly requested it
        // This should skip the normal AI response and only send handoff message
        if ($response->customerRequestedHandoff) {
            // Deactivate bot and initiate handoff
            $this->chatbotService->initiateHandoff($conversation, 'طلب العميل التحدث مع موظف');

            // Send ONLY handoff message to customer (skip AI response)
            $handoffMessage = $this->getHandoffMessageWithWorkingHours(
                $conversation,
                $response->language ?? 'ar'
            );
            $this->sendResponseAction->sendTextMessage($conversation, $handoffMessage);

            return $response;
        }

        // Handle response based on response_type (only if not handoff)
        if ($response->responseType === 'fallback') {
            // Send fallback message when AI cannot provide a useful response
            $settings = $this->chatbotService->getSettings($conversation->channel_id);
            $fallbackMessage = $settings?->getFallbackMessage($response->language)
                ?? ($response->language === 'ar'
                    ? 'عذراً، لم أفهم سؤالك. هل يمكنك إعادة صياغته؟'
                    : 'Sorry, I didn\'t understand your question. Could you please rephrase it?');
            $this->sendResponseAction->sendTextMessage($conversation, $fallbackMessage);

            // Mark as read when bot responds (not handoff)
            $this->markConversationAsRead($conversation);
        } elseif ($response->success && $response->message) {
            // Send the AI response to the customer
            $this->sendResponseAction->execute($conversation, $response);

            // Mark as read when bot responds (not handoff)
            $this->markConversationAsRead($conversation);
        }

        return $response;
    }

    /**
     * Get appropriate handoff message based on working hours
     */
    private function getHandoffMessageWithWorkingHours(Conversation $conversation, string $language): string
    {
        $settings = $this->chatbotService->getSettings($conversation->channel_id);

        // If no settings or within working hours, use normal handoff message
        if (!$settings || $settings->isWithinWorkingHours()) {
            return $this->getHandoffMessage($language);
        }

        // Outside working hours - use special message
        return $settings->getOutsideHoursMessage($language);
    }

    private function getHandoffMessage(string $language): string
    {
        return $language === 'ar'
            ? 'جاري تحويلك لأحد موظفي خدمة العملاء، يرجى الانتظار... 🙏'
            : 'Connecting you to a customer service representative, please wait... 🙏';
    }

    /**
     * Mark conversation as read when bot responds (shows "seen" to customer)
     */
    private function markConversationAsRead(Conversation $conversation): void
    {
        $workspace = $conversation->channel?->workspaces()?->first();

        if ($workspace) {
            $this->conversationService->markAsRead($workspace, $conversation);
        }
    }
}
