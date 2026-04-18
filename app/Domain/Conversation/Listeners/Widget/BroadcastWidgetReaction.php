<?php

namespace App\Domain\Conversation\Listeners\Widget;

use App\Domain\Conversation\Events\Widget\WidgetReactionSent;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastWidgetReaction
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler,
    ) {}

    public function handle(WidgetReactionSent $event): void
    {
        $this->messageHandler->handleReactionUpdate(
            $event->message,
            $event->conversation,
            $event->emoji
        );
    }
}
