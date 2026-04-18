<?php

namespace App\Domain\WhatsApp\Services;

use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Domain\WhatsApp\DTOs\MessageStatusDTO;
use App\Domain\WhatsApp\DTOs\WebhookPayloadDTO;
use App\Domain\WhatsApp\Actions\HandleStatusUpdateAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleTextMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleImageMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleVideoMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleAudioMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleDocumentMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleReactionMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleButtonMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleInteractiveMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleStickerMessageAction;
use App\Domain\WhatsApp\Actions\MessageHandlers\HandleLocationMessageAction;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookService
{
    public function __construct(
        private HandleTextMessageAction $textHandler,
        private HandleImageMessageAction $imageHandler,
        private HandleVideoMessageAction $videoHandler,
        private HandleAudioMessageAction $audioHandler,
        private HandleDocumentMessageAction $documentHandler,
        private HandleReactionMessageAction $reactionHandler,
        private HandleButtonMessageAction $buttonHandler,
        private HandleInteractiveMessageAction $interactiveHandler,
        private HandleStatusUpdateAction $statusHandler,
        private HandleStickerMessageAction $stickerHandler,
        private HandleLocationMessageAction $locationHandler,
    ) {}

    /**
     * Handle the complete webhook notification
     */
    public function handleNotification(array $notification): void
    {
        if (empty($notification['entry'])) {
            Log::warning("No 'entry' found in the notification.");
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
        if (empty($entry['id'])) {
            Log::warning("No 'id' found in the entry.");
            return;
        }

        $whatsappBusinessAccountId = $entry['id'];

        if (empty($entry['changes'])) {
            Log::warning("No 'changes' found for WhatsApp Business Account ID: $whatsappBusinessAccountId");
            return;
        }

        foreach ($entry['changes'] as $change) {
            $this->processChange($whatsappBusinessAccountId, $change);
        }
    }

    /**
     * Process a single change from the entry
     */
    private function processChange(string $whatsappBusinessAccountId, array $change): void
    {
        if (empty($change['field']) || empty($change['value'])) {
            Log::error("Invalid 'change' structure for WhatsApp Business Account ID: $whatsappBusinessAccountId");
            return;
        }

        $field = $change['field'];
        $value = $change['value'];

        if ($field === 'messages') {
            $this->handleMessagesEvent($whatsappBusinessAccountId, $value);
        } else {
            Log::info("Unhandled webhook field: $field");
        }
    }

    /**
     * Handle messages event
     */
    private function handleMessagesEvent(string $whatsappBusinessAccountId, array $value): void
    {
        if (empty($value['metadata']['phone_number_id'])) {
            Log::warning("Missing phone number ID in metadata for WABA ID: $whatsappBusinessAccountId");
            return;
        }

        $payload = WebhookPayloadDTO::fromWebhookEntry($whatsappBusinessAccountId, $value);
        // Handle statuses
        if ($payload->hasStatuses()) {
            $this->handleStatuses($payload->getStatusDTOs());
        }

        // Handle messages
        if ($payload->hasMessages()) {
            $this->handleMessages($payload->getIncomingMessageDTOs());
        }

        Log::info("Handled message event", $value);
    }

    /**
     * Handle message statuses
     */
    private function handleStatuses(array $statusDTOs): void
    {
        foreach ($statusDTOs as $statusDTO) {
            $this->statusHandler->execute($statusDTO);
        }
    }

    /**
     * Handle incoming messages
     */
    private function handleMessages(array $messageDTOs): void
    {
        foreach ($messageDTOs as $messageDTO) {
            $this->routeMessage($messageDTO);
        }
    }

    /**
     * Route message to appropriate handler
     */
    private function routeMessage(IncomingMessageDTO $dto): void
    {
        $handler = match ($dto->type) {
            'text' => $this->textHandler,
            'image' => $this->imageHandler,
            'video' => $this->videoHandler,
            'audio' => $this->audioHandler,
            'document' => $this->documentHandler,
            'reaction' => $this->reactionHandler,
            'button' => $this->buttonHandler,
            'interactive' => $this->interactiveHandler,
            'sticker' => $this->stickerHandler,
            'location' => $this->locationHandler,
            default => null,
        };

        if ($handler) {
            $handler->execute($dto);
        } else {
            Log::error("Unhandled message type: {$dto->type}", [
                'payload' => $dto->payload,
                'from' => $dto->sender->waId ?? null,
            ]);
        }
    }
}
