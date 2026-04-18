<?php

namespace App\Events;

use App\Models\WhatsappMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a user responds to an interactive message (button/list).
 */
class WhatsappInteractiveResponseReceived
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The user's response message.
     */
    public WhatsappMessage $responseMessage;

    /**
     * The ID of the draft the original message was created from.
     */
    public int $draftId;

    /**
     * The type of reply (button_reply or list_reply).
     */
    public string $replyType;

    /**
     * The ID of the button/list item clicked.
     */
    public string $replyId;

    /**
     * The title of the button/list item clicked.
     */
    public ?string $replyTitle;

    /**
     * Create a new event instance.
     */
    public function __construct(
        WhatsappMessage $responseMessage,
        int $draftId,
        string $replyType,
        string $replyId,
        ?string $replyTitle = null
    ) {
        $this->responseMessage = $responseMessage;
        $this->draftId = $draftId;
        $this->replyType = $replyType;
        $this->replyId = $replyId;
        $this->replyTitle = $replyTitle;
    }
}