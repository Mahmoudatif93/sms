<?php

namespace App\Domain\Conversation\Events\LiveChat;

use App\Models\LiveChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveChatReactionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public LiveChatMessage $message,
        public ?string $emoji,
    ) {}
}
