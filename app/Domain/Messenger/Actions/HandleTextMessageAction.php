<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\MessengerMessageDTO;
use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaPage;
use App\Traits\ContactManager;
use App\Traits\ConversationManager;
use Illuminate\Support\Facades\Log;

class HandleTextMessageAction
{
    use ContactManager, ConversationManager;

    public function __construct(
        private MessengerMessageRepositoryInterface $repository
    ) {}

    public function execute(MessengerMessageDTO $dto, MetaPage $metaPage, MessengerConsumer $consumer, ?string $conversationId): void
    {
        $text = $dto->getText();

        // Create message record
        $messengerMessage = $this->repository->createMessage([
            'id' => $dto->messageId,
            'meta_page_id' => $metaPage->id,
            'conversation_id' => $conversationId,
            'sender_type' => MessengerConsumer::class,
            'sender_id' => $consumer->id,
            'recipient_type' => MetaPage::class,
            'recipient_id' => $metaPage->id,
            'sender_role' => MessengerMessage::MESSAGE_SENDER_ROLE_CONSUMER,
            'type' => MessengerMessage::MESSAGE_TYPE_TEXT,
            'direction' => MessengerMessage::MESSAGE_DIRECTION_RECEIVED,
            'status' => MessengerMessage::MESSAGE_STATUS_DELIVERED,
            'messageable_id' => null,
            'messageable_type' => null,
            'created_at' => $dto->timestamp,
            'updated_at' => $dto->timestamp,
        ]);

        // Create text content if exists
        if ($text) {
            $textMessage = $this->repository->createTextMessage($dto->messageId, $text);
            $this->repository->updateMessageable($dto->messageId, $textMessage);
        }

        Log::info("Messenger message received", ['psid' => $dto->senderId, 'text' => $text]);
    }
}
