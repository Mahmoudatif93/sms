<?php

namespace App\Domain\Chatbot\Events;

use App\Domain\Chatbot\DTOs\ChatbotResponseDTO;
use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BotResponseSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Conversation $conversation,
        public readonly ChatbotResponseDTO $response,
    ) {}
}
