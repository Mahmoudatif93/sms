<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\TelegramConfiguration;
use App\Models\TelegramFileMessage;
use App\Models\TelegramLocationMessage;
use App\Models\TelegramMessage;
use App\Models\TelegramReactionMessage;
use App\Models\TelegramTextMessage;
use App\Models\TelegramMessageStatus;

class TelegramMessageRepository implements TelegramMessageRepositoryInterface
{
    public function __construct(
        private TelegramMessage $model
    ) {}

    public function create(array $data): TelegramMessage
    {
        return $this->model->create($data);
    }

    public function update(TelegramMessage $message, array $data): bool
    {
        return $message->update($data);
    }

    public function findById(string $id): ?TelegramMessage
    {
        return $this->model->find($id);
    }

    public function findByIdInConversation(string $id, string $conversationId): ?TelegramMessage
    {
        return $this->model
            ->where('id', $id)
            ->where('conversation_id', $conversationId)
            ->first();
    }

    public function createTextMessage(
        string $conversationId,
        string $chatId,
        string $text,
        bool $fromAgent = false,
        ?string $replyToMessageId = null
    ): TelegramMessage {
        $textMessage = TelegramTextMessage::create(['text' => $text]);

        return $this->createForConversation($conversationId, [
            'chat_id'             => $chatId,
            'type'                => 'text',
            'messageable_id'      => $textMessage->id,
            'messageable_type'    => TelegramTextMessage::class,
            'from_agent'          => $fromAgent,
            'reply_to_message_id' => $replyToMessageId,
            'status'              => 'sent',
        ]);
    }

    public function createFileMessage(
        string $conversationId,
        string $chatId,
        string $type,
        ?string $caption = null,
        ?string $fileId = null,
        ?string $filePath = null,
        bool $fromAgent = false,
        ?string $replyToMessageId = null
    ): TelegramMessage {
        $fileMessage = TelegramFileMessage::create([
            'type'    => $type,
            'caption' => $caption,
            'file_id' => $fileId,
            'file_path' => $filePath,
        ]);

        return $this->createForConversation($conversationId, [
            'chat_id'             => $chatId,
            'type'                => $type,
            'messageable_id'      => $fileMessage->id,
            'messageable_type'    => TelegramFileMessage::class,
            'from_agent'          => $fromAgent,
            'reply_to_message_id' => $replyToMessageId,
            'status'              => 'sent',
        ]);
    }

    public function createLocationMessage(
        string $conversationId,
        string $chatId,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null,
        bool $fromAgent = false,
        ?string $replyToMessageId = null
    ): TelegramMessage {
        $locationMessage = TelegramLocationMessage::create([
            'latitude'  => $latitude,
            'longitude' => $longitude,
            'name'      => $name,
            'address'   => $address,
        ]);

        return $this->createForConversation($conversationId, [
            'chat_id'             => $chatId,
            'type'                => 'location',
            'messageable_id'      => $locationMessage->id,
            'messageable_type'    => TelegramLocationMessage::class,
            'from_agent'          => $fromAgent,
            'reply_to_message_id' => $replyToMessageId,
            'status'              => 'sent',
        ]);
    }

    public function upsertReaction(string $messageId, string $emoji, string $direction): TelegramMessage
    {
        $reaction = TelegramReactionMessage::updateOrCreate(
            [
                'telegram_message_id' => $messageId,
                'direction'           => $direction,
            ],
            [
                'emoji' => $emoji,
            ]
        );

        return $this->model->find($messageId);
    }

    public function deleteReaction(string $messageId, string $direction): bool
    {
        return TelegramReactionMessage::where('telegram_message_id', $messageId)
            ->where('direction', $direction)
            ->delete() > 0;
    }

    public function saveMessageStatus(string $messageId, string $status): void
    {
        TelegramMessageStatus::create([
            'telegram_message_id' => $messageId,
            'status'              => $status,
            'timestamp'           => now()->timestamp,
        ]);
    }

    public function getTelegramConfiguration(string $connectorId): ?TelegramConfiguration
    {
        return TelegramConfiguration::where('connector_id', $connectorId)->first();
    }

    public function createForConversation(string $conversationId, array $data): TelegramMessage
    {
        $data['conversation_id'] = $conversationId;
        return $this->model->create($data);
    }
}
