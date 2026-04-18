<?php

namespace App\Domain\Conversation\Events\Telegram;

use App\Models\TelegramMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramReactionUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TelegramMessage $message,
        public ?string $emoji,
    ) {}
}
