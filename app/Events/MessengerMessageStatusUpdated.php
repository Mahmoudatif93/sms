<?php

namespace App\Events;

use App\Models\MessengerMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessengerMessageStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MessengerMessage $messengerMessage;

    public function __construct(MessengerMessage $messengerMessage)
    {
        $this->messengerMessage = $messengerMessage;
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('whatsapp-chat-channel'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'new-status-update';
    }
}
