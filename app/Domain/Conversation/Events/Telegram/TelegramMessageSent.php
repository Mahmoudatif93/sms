<?php

namespace App\Domain\Conversation\Events\Telegram;

use App\Models\Conversation;
use App\Models\TelegramMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelegramMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TelegramMessage $message,
        public Conversation $conversation,
    ) {}
}
