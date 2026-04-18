<?php

namespace App\Domain\Conversation\Services;

use App\Domain\Conversation\DTOs\Widget\InitializeChatDTO;
use App\Domain\Conversation\DTOs\Widget\WidgetMessageDTO;
use App\Domain\Conversation\DTOs\Widget\PreChatFormDTO;
use App\Domain\Conversation\DTOs\Widget\PostChatFormDTO;
use App\Domain\Conversation\DTOs\Widget\WidgetReactionDTO;
use App\Domain\Conversation\DTOs\Widget\UpdateWidgetSettingsDTO;
use App\Domain\Conversation\Actions\Widget\InitializeChatAction;
use App\Domain\Conversation\Actions\Widget\SendWidgetMessageAction;
use App\Domain\Conversation\Actions\Widget\SubmitPreChatFormAction;
use App\Domain\Conversation\Actions\Widget\SubmitPostChatFormAction;
use App\Domain\Conversation\Actions\Widget\SendWidgetReactionAction;
use App\Domain\Conversation\Actions\Widget\GetChatHistoryAction;
use App\Domain\Conversation\Actions\Widget\EndConversationAction;
use App\Domain\Conversation\Actions\Widget\CloseChatAction;
use App\Domain\Conversation\Actions\Widget\MarkMessagesAsReadAction;
use App\Domain\Conversation\Actions\Widget\MarkMessagesAsDeliveredAction;
use App\Domain\Conversation\Actions\Widget\SessionHeartbeatAction;
use App\Domain\Conversation\Actions\Widget\UpdateWidgetSettingsAction;
use App\Domain\Conversation\Actions\Widget\GetPreviousConversationsAction;
use App\Domain\Conversation\Events\Widget\WidgetMessageSent;
use App\Domain\Conversation\Events\Widget\WidgetReactionSent;
use App\Domain\Conversation\Events\Widget\WidgetConversationClosed;
use App\Domain\Conversation\Events\Widget\WidgetMessageStatusUpdated;
use App\Domain\Chatbot\Actions\ProcessIncomingMessageAction;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;

class LiveChatWidgetService
{
    public function __construct(
        private InitializeChatAction $initializeChatAction,
        private SendWidgetMessageAction $sendMessageAction,
        private SubmitPreChatFormAction $submitPreChatFormAction,
        private SubmitPostChatFormAction $submitPostChatFormAction,
        private SendWidgetReactionAction $sendReactionAction,
        private GetChatHistoryAction $getChatHistoryAction,
        private EndConversationAction $endConversationAction,
        private CloseChatAction $closeChatAction,
        private MarkMessagesAsReadAction $markAsReadAction,
        private MarkMessagesAsDeliveredAction $markAsDeliveredAction,
        private SessionHeartbeatAction $heartbeatAction,
        private UpdateWidgetSettingsAction $updateSettingsAction,
        private GetPreviousConversationsAction $getPreviousConversationsAction,
    ) {}

    public function initializeChat(InitializeChatDTO $dto): array
    {
        return $this->initializeChatAction->execute($dto);
    }

    public function sendMessage(WidgetMessageDTO $dto): array
    {
        $result = $this->sendMessageAction->execute($dto);

        // Dispatch event for broadcasting
        $conversation = Conversation::find($dto->sessionId);
        $message = \App\Models\LiveChatMessage::find($result['id']);

        if ($message && $conversation) {
            event(new WidgetMessageSent($message, $conversation));

            // Process chatbot if enabled (only for text messages)
            if ($dto->isTextMessage() && !empty($dto->message)) {
                $this->processChatbotIfEnabled($conversation, $dto->message);
            }
        }

        return $result;
    }

    /**
     * Process chatbot if enabled for this channel
     */
    private function processChatbotIfEnabled(Conversation $conversation, string $textContent): void
    {
        try {
            $chatbotAction = app(ProcessIncomingMessageAction::class);
            $chatbotAction->execute($conversation, $textContent);
        } catch (\Exception $e) {
            Log::warning('Chatbot processing failed for LiveChat', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function submitPreChatForm(PreChatFormDTO $dto): array
    {
        $result = $this->submitPreChatFormAction->execute($dto);

        // Dispatch event for broadcasting (pre-chat form message)
        if (isset($result['message']) && isset($result['conversation'])) {
            event(new WidgetMessageSent($result['message'], $result['conversation']));
        }

        // Remove internal data before returning
        unset($result['message'], $result['conversation']);

        return $result;
    }

    public function submitPostChatForm(PostChatFormDTO $dto): array
    {
        $result = $this->submitPostChatFormAction->execute($dto);

        // Dispatch event for broadcasting (post-chat form message)
        if (isset($result['message']) && isset($result['conversation'])) {
            event(new WidgetMessageSent($result['message'], $result['conversation']));
        }

        // Remove internal data before returning
        unset($result['message'], $result['conversation']);

        return $result;
    }

    public function sendReaction(WidgetReactionDTO $dto): array
    {
        $result = $this->sendReactionAction->execute($dto);

        // Dispatch event for broadcasting
        $conversation = Conversation::find($dto->sessionId);
        $message = \App\Models\LiveChatMessage::find($dto->messageId);

        if ($message && $conversation) {
            event(new WidgetReactionSent($message, $conversation, $dto->emoji));
        }

        return $result;
    }

    public function getChatHistory(string $sessionId, ?string $beforeId, int $limit): array
    {
        return $this->getChatHistoryAction->execute($sessionId, $beforeId, $limit);
    }

    public function endConversation(string $sessionId): array
    {
        return $this->endConversationAction->execute($sessionId);
    }

    public function closeChat(string $sessionId): array
    {
        $result = $this->closeChatAction->execute($sessionId);

        // Dispatch event for broadcasting
        if (isset($result['message']) && isset($result['conversation'])) {
            event(new WidgetConversationClosed($result['conversation'], $result['message']));
        }

        // Remove internal data before returning
        unset($result['message'], $result['conversation']);

        return $result;
    }

    public function markMessagesAsRead(string $sessionId, ?array $messageIds): array
    {
        $result = $this->markAsReadAction->execute($sessionId, $messageIds);

        // Dispatch status update events
        foreach ($result['messages'] as $message) {
            event(new WidgetMessageStatusUpdated($message, 'read'));
        }

        return ['updated_count' => $result['updated_count']];
    }

    public function markMessagesAsDelivered(array $messageIds): array
    {
        $result = $this->markAsDeliveredAction->execute($messageIds);

        // Dispatch status update events
        foreach ($result['messages'] as $message) {
            event(new WidgetMessageStatusUpdated($message, 'delivered'));
        }

        return ['updated_count' => $result['updated_count']];
    }

    public function sessionHeartbeat(string $sessionId): array
    {
        return $this->heartbeatAction->execute($sessionId);
    }

    public function updateWidgetSettings(UpdateWidgetSettingsDTO $dto): array
    {
        return $this->updateSettingsAction->execute($dto);
    }

    public function getPreviousConversations(string $contactId, string $widgetId): array
    {
        return $this->getPreviousConversationsAction->execute($contactId, $widgetId);
    }
}
