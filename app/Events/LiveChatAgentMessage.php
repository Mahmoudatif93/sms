<?php

namespace App\Events;

use App\Models\LiveChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveChatAgentMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public LiveChatMessage $liveChatMessage;
    public string $sessionId;

    /**
     * Create a new event instance.
     */
    public function __construct(LiveChatMessage $liveChatMessage)
    {
        $this->liveChatMessage = $liveChatMessage;
        $this->sessionId = $liveChatMessage->session_id;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // We broadcast both to the general channel and a session-specific channel
        return [
            new Channel('livechat-channel'),
            new PrivateChannel('livechat.session.' . $this->sessionId),
        ];
    }

    /**
     * Get the event name to broadcast.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'new-agent-message';
    }
}