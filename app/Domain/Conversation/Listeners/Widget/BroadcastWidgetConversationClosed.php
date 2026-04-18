<?php

namespace App\Domain\Conversation\Listeners\Widget;

use App\Domain\Conversation\Events\Widget\WidgetConversationClosed;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastWidgetConversationClosed
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler,
    ) {}

    public function handle(WidgetConversationClosed $event): void
    {
        // First broadcast the closing message
        $this->messageHandler->handleIncomingMessage(
            $event->message,
            $event->conversation
        );

        // Then broadcast the conversation closed event
        $this->messageHandler->handleConversationClosed($event->conversation);
    }
}
