<?php

namespace App\Domain\WhatsApp\Actions;

use App\Domain\Chatbot\Jobs\ProcessChatbotMessageJob;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Domain\WhatsApp\Repositories\WhatsAppMessageRepositoryInterface;
use App\Events\UnifiedMessageEvent;
use App\Http\Responses\Conversation;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Conversation as ConversationModel;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Traits\ConversationManager;
use App\Traits\AutoTranslationHandler;

abstract class BaseIncomingMessageAction
{
    use ConversationManager, AutoTranslationHandler;

    public function __construct(
        protected WhatsAppMessageRepositoryInterface $repository
    ) {
    }

    /**
     * Execute the message handling
     */
    abstract public function execute(IncomingMessageDTO $dto): ?WhatsappMessage;

    /**
     * Get the message type constant
     */
    abstract protected function getMessageType(): string;

    /**
     * Create the specific message content (text, image, etc.)
     * Returns the messageable object
     */
    abstract protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object;

    /**
     * Get text content for auto-translation (if applicable)
     */
    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return null;
    }

    /**
     * Check if message already exists
     */
    protected function messageExists(string $messageId): bool
    {
        return $this->repository->messageExists($messageId);
    }

    /**
     * Create or find the sender
     */
    protected function createSender(IncomingMessageDTO $dto): object
    {
        return $this->repository->findOrCreateSender(
            $dto->sender->phoneNumber,
            $dto->sender->waId,
            $dto->sender->whatsappBusinessAccountId,
            $dto->sender->name
        );
    }

    /**
     * Create the base WhatsApp message record
     */
    protected function createBaseMessage(IncomingMessageDTO $dto, object $sender): WhatsappMessage
    {
        return $this->repository->createMessage([
            'id' => $dto->messageId,
            'whatsapp_phone_number_id' => $dto->phoneNumberId,
            'sender_type' => get_class($sender),
            'sender_id' => $sender->id,
            'recipient_id' => $dto->phoneNumberId,
            'recipient_type' => WhatsappPhoneNumber::class,
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_CONSUMER,
            'type' => $this->getMessageType(),
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
            'status' => WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            'replied_to_message_id' => $dto->context->repliedToMessageId,
            'replied_to_message_from' => $dto->context->repliedToMessageFrom,
        ]);
    }

    /**
     * Save message status
     */
    protected function saveMessageStatus(string $messageId, string $timestamp): void
    {
        $this->repository->saveMessageStatus(
            $messageId,
            WhatsappMessage::MESSAGE_STATUS_DELIVERED,
            $timestamp
        );
    }

    /**
     * Update messageable relation
     */
    protected function updateMessageable(string $messageId, object $messageable): void
    {
        $this->repository->updateMessageable($messageId, $messageable);
    }

    /**
     * Start conversation and dispatch event
     */
    protected function handleConversationAndBroadcast(
        WhatsappMessage $whatsappMessage,
        IncomingMessageDTO $dto
    ): void {
        $conversation = $this->startConversationFromWhatsappMessage(
            $whatsappMessage,
            $dto->sender->whatsappBusinessAccountId,
            $dto->sender->waId,
            $dto->sender->name
        );

        if ($conversation) {
            // Process auto-translation if there's text content
            $textContent = $this->getTextForTranslation($dto);
            if (!empty($textContent)) {
                $this->processAutoTranslation($whatsappMessage, $conversation, $textContent);

                // Process chatbot if enabled
                $this->processChatbotIfEnabled($conversation, $textContent);
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

    /**
     * Process chatbot if enabled for this channel
     * Dispatches to background queue for faster webhook response
     */
    protected function processChatbotIfEnabled(ConversationModel $conversation, string $textContent): void
    {
        ProcessChatbotMessageJob::dispatch($conversation, $textContent);
    }

    /**
     * Common execution flow for most message types
     */
    protected function executeCommon(IncomingMessageDTO $dto): ?WhatsappMessage
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

        // Create specific message content
        $messageable = $this->createMessageContent($dto, $whatsappMessage);
        // Update messageable relation
        $this->updateMessageable($dto->messageId, $messageable);
        // Refresh to get updated messageable relation
        $whatsappMessage->refresh();

        // Handle conversation and broadcast
        $this->handleConversationAndBroadcast($whatsappMessage, $dto);

        return $whatsappMessage;
    }
}
