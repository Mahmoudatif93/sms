<?php

namespace App\Domain\Conversation\Listeners;

use App\Domain\Conversation\Events\LiveChat\LiveChatMessageSent;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastLiveChatMessage
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler
    ) {}

    public function handle(LiveChatMessageSent $event): void
    {
        $this->messageHandler->handleAgentIncomingMessage(
            $event->message,
            $event->conversation
        );
    }
}
