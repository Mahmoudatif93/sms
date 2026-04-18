<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\LiveChatConfiguration;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatMessage;
use App\Models\LiveChatMessageStatus;
use App\Models\LiveChatReactionMessage;
use App\Models\LiveChatTextMessage;

class LiveChatMessageRepository implements LiveChatMessageRepositoryInterface
{
    public function __construct(
        private LiveChatMessage $model
    ) {}

    public function create(array $data): LiveChatMessage
    {
        return $this->model->create($data);
    }

    public function update(LiveChatMessage $message, array $data): bool
    {
        return $message->update($data);
    }

    public function findById(string $id): ?LiveChatMessage
    {
        return $this->model->find($id);
    }

    public function findByIdInConversation(string $id, string $conversationId): ?LiveChatMessage
    {
        return $this->model
            ->where('id', $id)
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function createTextMessage(string $text): LiveChatTextMessage
    {
        return LiveChatTextMessage::create([
            'text' => $text,
        ]);
    }

    public function createFileMessage(?string $caption = null): LiveChatFileMessage
    {
        return LiveChatFileMessage::create([
            'caption' => $caption,
        ]);
    }

    public function upsertReaction(string $messageId, string $emoji, string $direction): LiveChatReactionMessage
    {
        return LiveChatReactionMessage::updateOrCreate(
            [
                'livechat_message_id' => $messageId,
                'direction' => $direction,
            ],
            [
                'emoji' => $emoji,
            ]
        );
    }

    public function deleteReaction(string $messageId, string $direction): bool
    {
        return LiveChatReactionMessage::where('livechat_message_id', $messageId)
            ->where('direction', $direction)
            ->delete() > 0;
    }

    public function saveMessageStatus(string $messageId, string $status): void
    {
        LiveChatMessageStatus::create([
            'livechat_message_id' => $messageId,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ]);
    }

    public function getLiveChatConfiguration(string $connectorId): ?LiveChatConfiguration
    {
        return LiveChatConfiguration::where('connector_id', $connectorId)->first();
    }

    public function createForConversation(string $conversationId, array $data): LiveChatMessage
    {
        $data['conversation_id'] = $conversationId;
        return $this->model->create($data);
    }
}
