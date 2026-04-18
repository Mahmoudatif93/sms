<?php

namespace App\Events;

use App\Http\Responses\Conversation;
use App\Models\LiveChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveChatIncomingMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LiveChatMessage $liveChatMessage;
    public Conversation $conversation;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveChatMessage $liveChatMessage,Conversation $conversation)
    {
        $this->liveChatMessage = $liveChatMessage;
        $this->conversation = $conversation;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('conversation-channel'),
        ];
    }

    /**
     * Get the event name to broadcast.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'new-incoming-message';
    }
}