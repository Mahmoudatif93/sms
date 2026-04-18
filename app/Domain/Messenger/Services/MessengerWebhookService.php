<?php

namespace App\Domain\Messenger\Services;

use App\Domain\Messenger\DTOs\MessageStatusDTO;
use App\Domain\Messenger\DTOs\MessengerMessageDTO;
use App\Domain\Messenger\DTOs\MessengerWebhookPayloadDTO;
use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Domain\Messenger\Actions\HandleTextMessageAction;
use App\Domain\Messenger\Actions\HandleMessageStatusAction;
use App\Traits\ContactManager;
use App\Traits\ConversationManager;
use Illuminate\Support\Facades\Log;

class MessengerWebhookService
{
    use ContactManager, ConversationManager;

    public function __construct(
        private MessengerMessageRepositoryInterface $repository,
        private HandleTextMessageAction $textHandler,
        private HandleMessageStatusAction $statusHandler,
    ) {}

    /**
     * Handle the complete webhook notification
     */
    public function handleNotification(array $notification): void
    {
        if (empty($notification['entry'])) {
            Log::warning("No 'entry' found in the Messenger notification.");
            return;
        }

        foreach ($notification['entry'] as $entry) {
            $this->processEntry($entry);
        }
    }

    /**
     * Process a single entry from the webhook
     */
    private function processEntry(array $entry): void
    {
        $payload = MessengerWebhookPayloadDTO::fromWebhookEntry($entry);
        if (empty($payload->pageId)) {
            Log::warning("No Page ID found in the entry.");
            return;
        }
        if (!$payload->hasMessagingEvents()) {
            Log::warning("No 'messaging' field found for Page ID: {$payload->pageId}");
            return;
        }
        foreach ($payload->getMessageDTOs() as $messageDTO) {
            $this->processMessage($messageDTO);
        }

        foreach ($payload->getStatusDTOs() as $statusDTO) {
            $this->processStatus($statusDTO);
        }
    }

    private function processStatus(MessageStatusDTO $dto): void
    {
        $this->statusHandler->execute($dto);
    }

    /**
     * Process a single message
     */
    private function processMessage(MessengerMessageDTO $dto): void
    {
        if (!$dto->isValid()) {
            Log::warning("Invalid Messenger message payload", ['messageId' => $dto->messageId]);
            return;
        }
       
        $metaPage = $this->repository->findMetaPage($dto->pageId);
        if (!$metaPage) {
            Log::warning("MetaPage not found for ID: {$dto->pageId}");
            return;
        }

        // Get consumer name from API
        $accessToken = $this->repository->getPageAccessToken($metaPage);
        $name = $this->getNameFromMessengerAPI(
            psid: $dto->senderId,
            pageId: $dto->pageId,
            pageAccessToken: $accessToken
        );

        // Find or create consumer
        $consumer = $this->repository->findOrCreateConsumer($dto->senderId, $metaPage->id, $name);

        // Find or create contact
        $contact = $consumer->contact ?? $this->createContactFromMessengerConsumer(
            messengerConsumer: $consumer,
            workspaceId: $metaPage->workspace_id
        );

        // Start conversation
        $conversation = $this->startConversation(
            platform: "messenger",
            channel: $metaPage->channel,
            contact: $contact
        );

        // Route to appropriate handler
        $this->routeMessage($dto, $metaPage, $consumer, $conversation?->id);
    }

    /**
     * Route message to appropriate handler based on type
     */
    private function routeMessage(MessengerMessageDTO $dto, $metaPage, $consumer, ?string $conversationId): void
    {
        switch ($dto->type) {
            case 'text':
                $this->textHandler->execute($dto, $metaPage, $consumer, $conversationId);
                break;
            default:
                Log::info("Unhandled Messenger message type", ['type' => $dto->type]);
                break;
        }
    }
}
