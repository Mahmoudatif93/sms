<?php

namespace App\Domain\Conversation\Listeners;

use App\Domain\Conversation\Events\LiveChat\LiveChatReactionUpdated;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastLiveChatReaction
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler
    ) {}

    public function handle(LiveChatReactionUpdated $event): void
    {
        $this->messageHandler->handleAgentReactionUpdate(
            $event->message,
            $event->emoji
        );
    }
}
