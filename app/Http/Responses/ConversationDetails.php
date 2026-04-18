<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\PreChatFormFieldResponse;
use App\Models\User;
use App\Traits\ContactManager;
use App\Traits\ConversationMessagesManager;
use Illuminate\Database\Eloquent\Collection;

class ConversationDetails extends DataInterface
{
    use ContactManager, ConversationMessagesManager;

    public string $id;
    public string $platform;
    public string $channel_id;
    public ?string $contact_display;
    public string $status;
    public int $unread_notifications_count;
    public array $messages;
    public ?InboxAgent $inboxAgent;
    public ?Contact $contact;
    public ?array $preChatFormFieldResponse;
    public int $created_at;
    public ?string $last_message_date;

    private array $translationOptions = [];
    public mixed $workspace;

    public function __construct(Conversation $conversation, array $options = [])
    {
        $this->id = $conversation->id;
        $this->platform = $conversation->platform;
        $this->channel_id = $conversation->channel_id;

        $this->status = $conversation->status;
        $this->created_at = $conversation->created_at;


        $this->translationOptions = [
            'translate' => $options['translate'] ?? false,
            'lang' => $options['lang'] ?? 'en'
        ];

        // Build contact details
        $this->contact_display = $this->getContactName($conversation->contact, $this->platform);
        $this->contact = $conversation->contact ? new Contact($conversation->contact) : null;

        // Get messages
        $this->messages = $this->loadFormattedMessages($conversation, $options);
        $this->unread_notifications_count = $conversation->countUnread();
        $this->preChatFormFieldResponse = PreChatFormFieldResponse::getConversationResponses($conversation->id);

        // Load agent
        $currentAgent = $conversation->currentAgent();
        $this->inboxAgent = $currentAgent instanceof User ? new InboxAgent($currentAgent) : null;
        $this->workspace = $conversation->workspace ? new Workspace($conversation->workspace): null;
    }
}
