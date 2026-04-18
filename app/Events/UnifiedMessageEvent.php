<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UnifiedMessageEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public string $broadcastQueue = 'sms-low'; 
    public string $platform;
    public string $eventType;
    public array $payload;
    public string $channelId;
    public ?string $conversationId;
    public ?string $workspaceId;

    /**
     * Create a new event instance.
     *
     * @param string $platform Platform identifier (whatsapp, livechat, messenger, etc.)
     * @param string $eventType Event type (message, status, etc.)
     * @param array $payload Event payload data
     * @param string $channelId Channel ID
     * @param string|null $conversationId Conversation ID if applicable
     */
    public function __construct(
        string $platform,
        string $eventType,
        array $payload,
        string $channelId,
        ?string $conversationId = null,
        ?string $workspaceId = null
    ) {
        $this->platform = $platform;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->channelId = $channelId;
        $this->conversationId = $conversationId;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        if($this->conversationId){
                 $channels[] = new Channel("unified-messaging-{$this->conversationId}");
        } else if ($this->workspaceId) {
            $channels[] = new Channel("unified-messaging-{$this->workspaceId}");
        }       
        else {
            $channels[] = new Channel("unified-messaging-whatsapp");
        }

        return $channels;
    }

    /**
     * Get the event name to broadcast.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        // Two event types: 'new-message' or 'status-update'
        return $this->eventType;
    }
}
