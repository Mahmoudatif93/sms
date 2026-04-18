<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\MessageTranslation;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappDocumentMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappLocationMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappReactionMessage;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappVideoMessage;

class WhatsAppMessageRepository implements WhatsAppMessageRepositoryInterface
{
    public function __construct(
        private WhatsappMessage $model
    ) {}

    public function create(array $data): WhatsappMessage
    {
        return $this->model->create($data);
    }

    public function update(WhatsappMessage $message, array $data): bool
    {
        return $message->update($data);
    }

    public function findById(string $id): ?WhatsappMessage
    {
        return $this->model->find($id);
    }

    public function findOrCreateConsumer(string $phoneNumber, string $businessAccountId, ?string $waId = null): WhatsappConsumerPhoneNumber
    {
        return WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $phoneNumber,
                'whatsapp_business_account_id' => $businessAccountId,
            ],
            ['wa_id' => $waId]
        );
    }

    public function createTextMessage(string $messageId, string $body, ?bool $previewUrl = null): WhatsappTextMessage
    {
        return WhatsappTextMessage::create([
            'whatsapp_message_id' => $messageId,
            'body' => $body,
            'preview_url' => $previewUrl,
        ]);
    }

    public function createImageMessage(string $messageId, ?string $mediaId, ?string $caption = null): WhatsappImageMessage
    {
        dd('2');
        return WhatsappImageMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'caption' => $caption,
        ]);
    }

    public function createVideoMessage(string $messageId, ?string $mediaId, ?string $caption = null): WhatsappVideoMessage
    {
        return WhatsappVideoMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'caption' => $caption,
        ]);
    }

    public function createAudioMessage(string $messageId, ?string $mediaId): WhatsappAudioMessage
    {
        return WhatsappAudioMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
        ]);
    }

    public function createDocumentMessage(string $messageId, ?string $mediaId, ?string $filename = null, ?string $caption = null): WhatsappDocumentMessage
    {
        return WhatsappDocumentMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'filename' => $filename,
            'caption' => $caption,
        ]);
    }

    public function createLocationMessage(string $messageId, float $latitude, float $longitude, ?string $name = null, ?string $address = null): WhatsappLocationMessage
    {
        return WhatsappLocationMessage::create([
            'whatsapp_message_id' => $messageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address,
        ]);
    }

    public function createReactionMessage(string $messageId, string $reactedMessageId, string $emoji, string $direction): WhatsappReactionMessage
    {
        return WhatsappReactionMessage::create([
            'message_id' => $messageId,
            'whatsapp_message_id' => $reactedMessageId,
            'emoji' => $emoji,
            'direction' => $direction
        ]);
    }

    public function deleteReactionByMessageId(string $messageId): bool
    {
        return WhatsappReactionMessage::where('message_id', $messageId)->delete() > 0;
    }

    public function saveMessageStatus(string $messageId, string $status): void
    {
        WhatsappMessageStatus::create([
            'whatsapp_message_id' => $messageId,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ]);
    }

    public function createTranslation(string $messageId, string $messageType, string $translatedText, ?string $targetLanguage): void
    {
        MessageTranslation::create([
            'messageable_id' => $messageId,
            'messageable_type' => $messageType,
            'source_language' => null,
            'target_language' => $targetLanguage,
            'translated_text' => $translatedText,
        ]);
    }
}
