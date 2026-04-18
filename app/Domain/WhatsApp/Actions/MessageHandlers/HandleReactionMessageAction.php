<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Events\UnifiedMessageEvent;
use App\Http\Responses\Conversation;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\WhatsappMessage;

class HandleReactionMessageAction extends BaseIncomingMessageAction
{
    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        // Skip if message already exists
        if ($this->messageExists($dto->messageId)) {
            return $this->repository->findMessage($dto->messageId);
        }

        // Create sender
        $sender = $this->createSender($dto);

        // Create base message
        $whatsappMessage = $this->createBaseMessage($dto, $sender);

        // Save status
        $this->saveMessageStatus($dto->messageId, $dto->timestamp);

        // Create reaction content
        $reactionMessage = $this->repository->createReactionMessage(
            $dto->messageId,
            $dto->getReactedMessageId(),
            $dto->getReactionEmoji()
        );

        // Update messageable relation
        $this->updateMessageable($dto->messageId, $reactionMessage);

        // Handle conversation and update reaction with correct message ID
        $this->handleReactionConversation($whatsappMessage, $dto, $reactionMessage);

        return $whatsappMessage;
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_REACTION;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        return $this->repository->createReactionMessage(
            $dto->messageId,
            $dto->getReactedMessageId(),
            $dto->getReactionEmoji()
        );
    }

    private function handleReactionConversation(
        WhatsappMessage $whatsappMessage,
        IncomingMessageDTO $dto,
        object $reactionMessage
    ): void {
        $conversation = $this->startConversationFromWhatsappMessage(
            $whatsappMessage,
            $dto->sender->whatsappBusinessAccountId,
            $dto->sender->waId,
            $dto->sender->name
        );

        if ($conversation) {
            // Find original message and update reaction
            $originalMessage = $this->repository->findOriginalMessageForReaction(
                $dto->getReactedMessageId(),
                $conversation->id
            );

            if ($originalMessage) {
                $reactionMessage->update([
                    'message_id' => $originalMessage->id,
                ]);
            }

            // Dispatch unified message event
            event(new UnifiedMessageEvent(
                Channel::WHATSAPP_PLATFORM,
                'new-message',
                [
                    'message_id' => $whatsappMessage->id,
                    'conversation_id' => $conversation->id,
                    'conversation' => [
                        'data' => new Conversation($conversation),
                    ],
                    'message' => [
                        'data' => new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
                    ],
                ],
                $conversation->channel_id,
                null,
                $conversation->workspace_id
            ));
        }
    }
}
