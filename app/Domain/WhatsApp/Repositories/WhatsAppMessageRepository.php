<?php

namespace App\Domain\WhatsApp\Repositories;

use App\Logging\MetaConversationTextLogs;
use App\Models\MetaConversationLog;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappDocumentMessage;
use App\Models\WhatsappFlowResponseMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappMessageStatusError;
use App\Models\WhatsappReactionMessage;
use App\Models\WhatsappTextMessage;
use App\Models\WhatsappVideoMessage;
use App\Models\WhatsappStickerMessage;
use App\Models\WhatsappLocationMessage;

class WhatsAppMessageRepository implements WhatsAppMessageRepositoryInterface
{
    public function messageExists(string $messageId): bool
    {
        return WhatsappMessage::whereId($messageId)->exists();
    }

    public function findMessage(string $messageId): ?WhatsappMessage
    {
        return WhatsappMessage::find($messageId);
    }

    public function findOrCreateSender(
        string $phoneNumber,
        string $waId,
        string $whatsappBusinessAccountId,
        ?string $name = null
    ): WhatsappConsumerPhoneNumber {
        return WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $phoneNumber,
                'whatsapp_business_account_id' => $whatsappBusinessAccountId,
            ],
            [
                'wa_id' => $waId,
                'name' => $name,
            ]
        );
    }

    public function createMessage(array $data): WhatsappMessage
    {
        return WhatsappMessage::create($data);
    }

    public function updateMessageStatus(string $messageId, string $status, ?string $conversationId = null): bool
    {
        $message = $this->findMessage($messageId);
        if (!$message) {
            return false;
        }

        $updateData = ['status' => $status];
        // if ($conversationId) {
        //     $updateData['whatsapp_conversation_id'] = $conversationId;
        // }

        return $message->update($updateData);
    }

    public function saveMessageStatus(string $messageId, string $status, string $timestamp): void
    {
        WhatsappMessageStatus::updateOrCreate(
            [
                'whatsapp_message_id' => $messageId,
                'status' => $status,
            ],
            [
                'timestamp' => $timestamp,
            ]
        );
    }

    public function createStatusError(int $messageStatusId, array $errorData): void
    {
        WhatsappMessageStatusError::create([
            'whatsapp_message_status_id' => $messageStatusId,
            'error_code' => $errorData['code'] ?? null,
            'error_title' => $errorData['title'] ?? null,
            'error_message' => $errorData['message'] ?? null,
            'error_details' => $errorData['error_data']['details'] ?? null,
        ]);
    }

    public function createTextMessage(string $messageId, string $body, ?string $previewUrl = null): object
    {
        return WhatsappTextMessage::create([
            'whatsapp_message_id' => $messageId,
            'body' => $body,
            'preview_url' => $previewUrl,
        ]);
    }

    public function createImageMessage(string $messageId, string $mediaId, ?string $caption = null): object
    {
        return WhatsappImageMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'caption' => $caption,
        ]);
    }

    public function createVideoMessage(string $messageId, string $mediaId, ?string $caption = null): object
    {
        return WhatsappVideoMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'caption' => $caption,
        ]);
    }

    public function createAudioMessage(string $messageId, string $mediaId): object
    {
        return WhatsappAudioMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
        ]);
    }

    public function createDocumentMessage(
        string $messageId,
        string $mediaId,
        ?string $filename = null,
        ?string $caption = null,
        ?string $link = null
    ): object {
        return WhatsappDocumentMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'link' => $link,
            'filename' => $filename,
            'caption' => $caption,
        ]);
    }

    public function createReactionMessage(string $messageId, string $reactedMessageId, ?string $emoji = null): object
    {
        return WhatsappReactionMessage::create([
            'whatsapp_message_id' => $messageId,
            'message_id' => $reactedMessageId,
            'emoji' => $emoji,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_RECEIVED,
        ]);
    }

    public function createInteractiveMessage(string $messageId, array $interactiveData): object
    {
        return WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $messageId,
            'interactive_type' => $interactiveData['type'],
            'button_reply_id' => $interactiveData['button_reply_id'] ?? null,
            'button_reply_title' => $interactiveData['button_reply_title'] ?? null,
            'list_reply_id' => $interactiveData['list_reply_id'] ?? null,
            'list_reply_title' => $interactiveData['list_reply_title'] ?? null,
            'list_reply_description' => $interactiveData['list_reply_description'] ?? null,
            'payload' => $interactiveData['payload'] ?? null,
        ]);
    }

    public function createFlowResponseMessage(string $messageId, array $flowData): object
    {
        return WhatsappFlowResponseMessage::create([
            'whatsapp_message_id' => $messageId,
            'flow_token' => $flowData['flow_token'] ?? null,
            'name' => $flowData['name'] ?? null,
            'body' => $flowData['body'] ?? null,
            'response_json' => $flowData['response_json'] ?? null,
        ]);
    }

    public function updateMessageable(string $messageId, object $messageable): void
    {
        WhatsappMessage::whereId($messageId)->update([
            'messageable_id' => $messageable->id,
            'messageable_type' => get_class($messageable),
        ]);
    }

    public function logMetaConversation(array $data): void
    {
        $data['text_log'] = MetaConversationTextLogs::get($data['decision'] ?? '');
        MetaConversationLog::create($data);
    }

    public function findOriginalMessageForReaction(string $messageId, string $conversationId): ?WhatsappMessage
    {
        // Strategy 1: Try exact match first
        $message = $this->findMessage($messageId);
        if ($message) {
            return $message;
        }

        // Strategy 2: Find most recent message sent within last 24 hours
        return WhatsappMessage::where('conversation_id', $conversationId)
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();
    }


    public function createStickerMessage(
        string $messageId,
        string $mediaId,
        bool $isAnimated = false,
        ?string $mimeType = null
    ): object {
        return WhatsappStickerMessage::create([
            'whatsapp_message_id' => $messageId,
            'media_id' => $mediaId,
            'is_animated' => $isAnimated,
            'mime_type' => $mimeType,
        ]);
    }

    public function createLocationMessage(
        string $messageId,
        float $latitude,
        float $longitude,
        ?string $name = null,
        ?string $address = null
    ): object {
        return WhatsappLocationMessage::create([
            'whatsapp_message_id' => $messageId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'name' => $name,
            'address' => $address,
        ]);
    }
}
