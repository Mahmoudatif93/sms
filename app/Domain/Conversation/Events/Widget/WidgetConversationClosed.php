<?php

namespace App\Domain\Conversation\Events\Widget;

use App\Models\LiveChatMessage;
use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetConversationClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public LiveChatMessage $message,
    ) {}
}
