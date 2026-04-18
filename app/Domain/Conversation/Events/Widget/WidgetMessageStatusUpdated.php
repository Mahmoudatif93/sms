<?php

namespace App\Domain\Conversation\Events\Widget;

use App\Models\LiveChatMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WidgetMessageStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public LiveChatMessage $message,
        public string $status,
    ) {}
}
