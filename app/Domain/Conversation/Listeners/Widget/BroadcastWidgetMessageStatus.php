<?php

namespace App\Domain\Conversation\Listeners\Widget;

use App\Domain\Conversation\Events\Widget\WidgetMessageStatusUpdated;
use App\Services\Messaging\LiveChatMessageHandler;

class BroadcastWidgetMessageStatus
{
    public function __construct(
        private LiveChatMessageHandler $messageHandler,
    ) {}

    public function handle(WidgetMessageStatusUpdated $event): void
    {
        $this->messageHandler->handleStatusUpdate(
            $event->message,
            $event->status
        );
    }
}
