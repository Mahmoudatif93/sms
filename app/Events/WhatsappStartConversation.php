<?php

namespace App\Events;

use App\Models\WhatsappMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a user responds to an interactive message (button/list).
 */
class WhatsappStartConversation
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user's response message.
     */
    public WhatsappMessage $whatsappMessage;
    public string $conversationId;
    public bool $isCustomerServiceWindowActive;


    /**
     * Create a new event instance.
     */
    public function __construct(
        WhatsappMessage $whatsappMessage,
        string $conversationId,
        bool $isCustomerServiceWindowActive

    ) {

        $this->whatsappMessage = $whatsappMessage;
        $this->conversationId = $conversationId;
        $this->whatsappMessage->conversation_id = $this->conversationId;
        $this->isCustomerServiceWindowActive = $isCustomerServiceWindowActive;
    }
}