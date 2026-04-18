<?php

namespace App\Domain\Conversation\Repositories;

use App\Models\Widget;
use App\Models\Channel;
use App\Models\PreChatForm;
use App\Models\PostChatForm;
use App\Models\Conversation;
use App\Models\LiveChatMessage;
use App\Models\LiveChatConfiguration;
use App\Models\ContactEntity;
use Illuminate\Database\Eloquent\Collection;

class WidgetRepository implements WidgetRepositoryInterface
{
    public function __construct(
        private Widget $model
    ) {}

    public function findById(string $id): ?Widget
    {
        return $this->model->find($id);
    }

    public function findByIdOrFail(string $id): Widget
    {
        return $this->model->findOrFail($id);
    }

    public function update(Widget $widget, array $data): Widget
    {
        $widget->update($data);
        return $widget->refresh();
    }

    public function getChannelForWidget(string $widgetId): ?Channel
    {
        $liveChatConfig = LiveChatConfiguration::where('widget_id', $widgetId)->first();

        if (!$liveChatConfig) {
            return null;
        }

        return Channel::where('connector_id', $liveChatConfig->connector_id)
            ->where('platform', Channel::LIVECHAT_PLATFORM)
            ->where('status', Channel::STATUS_ACTIVE)
            ->first();
    }

    public function getPreChatForm(string $channelId, string $widgetId): ?PreChatForm
    {
        return PreChatForm::where('channel_id', $channelId)
            ->where('widget_id', $widgetId)
            ->first();
    }

    public function getPostChatForm(string $channelId, string $widgetId): ?PostChatForm
    {
        return PostChatForm::where('channel_id', $channelId)
            ->where('widget_id', $widgetId)
            ->first();
    }

    public function getActiveConversation(string $contactId, string $platform, Channel $channel): ?Conversation
    {
        return Conversation::where('contact_id', $contactId)
            ->where('channel_id', $channel->id)
            ->whereIn('status', [
                Conversation::STATUS_ACTIVE,
                Conversation::STATUS_WAITING,
                Conversation::STATUS_OPEN,
                Conversation::STATUS_PENDING,
            ])
            ->first();
    }

    public function hasEndedConversations(string $contactId, string $platform, Channel $channel): bool
    {
        return Conversation::where('contact_id', $contactId)
            ->where('channel_id', $channel->id)
            ->whereIn('status', [
                Conversation::STATUS_ENDED,
                Conversation::STATUS_CLOSED,
                Conversation::STATUS_ARCHIVED,
            ])
            ->exists();
    }

    public function getPreviousConversations(string $contactId, string $widgetId, int $limit = 5): Collection
    {
        return Conversation::where('contact_id', $contactId)
            ->where('widget_id', $widgetId)
            ->where('status', Conversation::STATUS_ENDED)
            ->orderBy('ended_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getChatHistory(string $conversationId, ?string $beforeId = null, int $limit = 50): Collection
    {
        $query = LiveChatMessage::where('conversation_id', $conversationId)
            ->with('reactionMessage')
            ->orderBy('created_at', 'desc');

        if ($beforeId) {
            $beforeMessage = LiveChatMessage::findOrFail($beforeId);
            $query->where('created_at', '<', $beforeMessage->created_at);
        }

        return $query->limit($limit)->get();
    }

    public function markMessagesAsRead(string $conversationId, ?array $messageIds = null): int
    {
        $query = LiveChatMessage::where('conversation_id', $conversationId)
            ->where('direction', LiveChatMessage::MESSAGE_STATUS_SENT)
            ->where(function ($q) {
                $q->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                    ->orWhere('is_read', false);
            });

        if ($messageIds) {
            $query->whereIn('id', $messageIds);
        }

        return $query->update([
            'is_read' => true,
            'read_at' => now(),
            'status' => LiveChatMessage::MESSAGE_STATUS_READ,
        ]);
    }

    public function markMessagesAsDelivered(array $messageIds): int
    {
        return LiveChatMessage::whereIn('id', $messageIds)
            ->where('status', LiveChatMessage::MESSAGE_STATUS_SENT)
            ->update([
                'status' => LiveChatMessage::MESSAGE_STATUS_DELIVERED,
            ]);
    }
}
