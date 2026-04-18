<?php

namespace App\Domain\Conversation\Events\Widget;

use App\Models\LiveChatMessage;
use App\Models\Conversation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetReactionSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LiveChatMessage $message,
        public Conversation $conversation,
        public ?string $emoji,
    ) {}
}
