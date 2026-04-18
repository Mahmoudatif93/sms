<?php

namespace App\Domain\Messenger\Repositories;

use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MessengerMessageStatus;
use App\Models\MessengerTextMessage;
use App\Models\MetaPage;

class MessengerMessageRepository implements MessengerMessageRepositoryInterface
{
    public function findMetaPage(string $pageId): ?MetaPage
    {
        return MetaPage::where('id', $pageId)->first();
    }

    public function findOrCreateConsumer(string $psid, string $metaPageId, ?string $name = null): MessengerConsumer
    {
        return MessengerConsumer::updateOrCreate(
            ['psid' => $psid, 'meta_page_id' => $metaPageId],
            ['name' => $name]
        );
    }

    public function createMessage(array $data): MessengerMessage
    {
        return MessengerMessage::updateOrCreate(
            ['id' => $data['id']],
            $data
        );
    }

    public function createTextMessage(string $messageId, string $text): object
    {
        return MessengerTextMessage::updateOrCreate(
            ['messenger_message_id' => $messageId],
            ['text' => $text]
        );
    }

    public function updateMessageable(string $messageId, object $messageable): void
    {
        MessengerMessage::where('id', $messageId)->update([
            'messageable_type' => get_class($messageable),
            'messageable_id' => $messageable->id,
        ]);
    }

    public function getPageAccessToken(MetaPage $metaPage): ?string
    {
        return $metaPage->accessTokens()->first()?->access_token;
    }

    public function findConsumerByPsid(string $psid, string $metaPageId): ?MessengerConsumer
    {
        return MessengerConsumer::where('psid', $psid)
            ->where('meta_page_id', $metaPageId)
            ->first();
    }

    public function getUnreadReceivedMessages(string $conversationId): \Illuminate\Support\Collection
    {
        return MessengerMessage::where('conversation_id', $conversationId)
            ->where('direction', MessengerMessage::MESSAGE_DIRECTION_RECEIVED)
            ->where('status', MessengerMessage::MESSAGE_STATUS_DELIVERED)
            ->get();
    }

    public function markMessageAsRead(string $messageId): bool
    {
        return MessengerMessage::where('id', $messageId)
            ->update(['status' => MessengerMessage::MESSAGE_STATUS_READ]) > 0;
    }

    public function getLastReceivedMessageId(string $conversationId): ?string
    {
        return MessengerMessage::where('conversation_id', $conversationId)
            ->where('direction', MessengerMessage::MESSAGE_DIRECTION_RECEIVED)
            ->orderByDesc('created_at')
            ->value('id');
    }

    public function createOutgoingMessage(array $data): MessengerMessage
    {
        return MessengerMessage::create($data);
    }

    public function findMessage(string $messageId): ?MessengerMessage
    {
        return MessengerMessage::with(['messageable'])->find($messageId);
    }

    public function updateMessageStatus(string $messageId, string $status): bool
    {
        return MessengerMessage::where('id', $messageId)
            ->update(['status' => $status]) > 0;
    }

    public function getMessagesBefore(
        string $pageId,
        string $consumerId,
        int $beforeTimestamp,
        string $direction,
        string $targetStatus
    ): \Illuminate\Support\Collection {
        // Determine which statuses to exclude based on target status
        $excludeStatuses = $this->getStatusesToExclude($targetStatus);
        return MessengerMessage::where('meta_page_id', $pageId)
            ->where('recipient_id', $consumerId)
            ->where('direction', $direction)
            // ->where('created_at', '<=', $beforeTimestamp)
            ->whereNotIn('status', $excludeStatuses)
            ->get();
    }

    private function getStatusesToExclude(string $targetStatus): array
    {
        // Only exclude statuses that are >= target status
        // delivered: exclude [delivered, read] - already delivered or read
        // read: exclude [read] only - can update delivered to read
        return match ($targetStatus) {
            MessengerMessage::MESSAGE_STATUS_DELIVERED => [
                MessengerMessage::MESSAGE_STATUS_DELIVERED,
                MessengerMessage::MESSAGE_STATUS_READ,
            ],
            MessengerMessage::MESSAGE_STATUS_READ => [
                MessengerMessage::MESSAGE_STATUS_READ,
            ],
            default => [],
        };
    }

    public function saveMessageStatus(string $messageId, string $status): void
    {
        MessengerMessageStatus::create([
            'messenger_message_id' => $messageId,
            'status' => $status,
            'timestamp' => now()->timestamp,
        ]);
    }
}
