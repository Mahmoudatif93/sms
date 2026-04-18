<?php

namespace App\Domain\Chatbot\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HandoffInitiated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("workspace.{$this->conversation->workspace_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chatbot.handoff';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'channel_id' => $this->conversation->channel_id,
            'contact_name' => $this->conversation->contact?->display_name,
            'reason' => $this->reason,
            'platform' => $this->conversation->platform,
        ];
    }
}
