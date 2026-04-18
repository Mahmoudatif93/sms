<?php

namespace App\Domain\Conversation\Listeners\Widget;

use App\Domain\Conversation\Events\Widget\WidgetMessageSent;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastWidgetMessage
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler,
    ) {}

    public function handle(WidgetMessageSent $event): void
    {
        $this->messageHandler->handleIncomingMessage(
            $event->message,
            $event->conversation
        );
    }
}
