<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\TicketEntity;
use App\Traits\ContactManager;
use Illuminate\Support\Carbon;

class Ticket extends DataInterface
{
    use ContactManager;
    /**
     * Ticket ID.
     *
     * @var string
     */
    public string $id;

    /**
     * Ticket number for public reference.
     *
     * @var string
     */
    public string $ticket_number;

    /**
     * The channel associated with the ticket.
     *
     * @var string
     */
    public string $channel_id;

    /**
     * The display name of the contact.
     *
     * @var string|null
     */
    public ?string $contact_display;

    /**
     * The channel name.
     * 
     * @var string|null
     */
    public ?string $channel_name;

    /**
     * The contact associated with the ticket.
     *
     * @var Contact
     */
    public Contact $contact;

    /**
     * The subject of the ticket.
     * 
     * @var string
     */
    public string $subject;

    /**
     * The status of the ticket.
     * 
     * @var string
     */
    public string $status;

    /**
     * The priority of the ticket.
     * 
     * @var string
     */
    public string $priority;

    /**
     * The assigned agent's name.
     * 
     * @var string
     */
    public string $assigned_to;

    /**
     * The assigned agent's ID.
     * 
     * @var string|null
     */
    public ?string $assigned_to_id;

    /**
     * Timestamp of the last message.
     * 
     * @var int|null
     */
    public ?int $last_message_timestamp;

    /**
     * ISO-formatted date of the last message.
     * 
     * @var string|null
     */
    public ?string $last_message_date;

    /**
     * Source of the ticket (conversation, email, iframe).
     * 
     * @var string
     */
    public string $source;

    /**
     * Conversation ID if the ticket originated from a conversation.
     * 
     * @var string|null
     */
    public ?string $conversation_id;

    /**
     * Creation date of the ticket in ISO format.
     * 
     * @var string
     */
    public string $created_at;

    /**
     * Last update date of the ticket in ISO format.
     * 
     * @var string
     */
    public string $updated_at;

    /**
     * Due date of the ticket in ISO format, if set.
     * 
     * @var string|null
     */
    public ?string $due_date;

    /**
     * Number of messages in the ticket.
     * 
     * @var int
     */
    public int $message_count;

    /**
     * Indicates if there are any unread messages for the current user.
     * 
     * @var bool
     */
    public bool $has_unread_messages = false;

    /**
     * Tags associated with the ticket.
     * 
     * @var array
     */
    public array $tags = [];
    /**
     * The assigned agent for the ticket.
     * 
     * @var inboxAgent|null
     */
    public ?InboxAgent $inboxAgent;

    /**
     * Create a new ticket response.
     *
     * @param TicketEntity $ticket
     */
    public function __construct(TicketEntity $ticket)
    {
        $this->id = $ticket->id;
        $this->ticket_number = $ticket->ticket_number;
        $this->channel_id = $ticket->channel_id;
        $this->channel_name = $ticket->channel?->name;
        $this->contact_display = $this->getContactName($ticket->contact,\App\Models\Channel::TICKETING_PLATFORM);
        $this->contact = new Contact($ticket->contact);
        $this->subject = $ticket->subject;
        $this->status = $ticket->status;
        $this->priority = $ticket->priority;
        $this->assigned_to = $ticket?->assignedAgent?->name ?? 'Unassigned';
        $this->assigned_to_id = $ticket?->assignedAgent?->id;
        $this->source = $ticket->source;
        $this->conversation_id = $ticket->conversation_id;
        $this->created_at = Carbon::parse($ticket->created_at)->toIso8601String();
        $this->updated_at = Carbon::parse($ticket->updated_at)->toIso8601String();
        $this->due_date = $ticket->due_date ? Carbon::parse($ticket->due_date)->toIso8601String() : null;
        $currentAgent = $ticket->currentAgent();
        $this->inboxAgent = $currentAgent instanceof \App\Models\User ? new InboxAgent($currentAgent) : null;

        // Get the last message timestamp
        $this->last_message_timestamp = $this->getLastMessageTimestamp($ticket);
        $this->last_message_date = $this->last_message_timestamp
            ? Carbon::createFromTimestamp($this->last_message_timestamp)->toIso8601String()
            : null;

        // Get message count
        $this->message_count = $this->getMessageCount($ticket);

        // Load tags if they exist
        if ($ticket->relationLoaded('tags')) {
            $this->tags = $ticket->tags->map(function ($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'color' => $tag->color
                ];
            })->toArray();
        }
        // $ticket->assignedAgent 
        // ? new TicketAgent($ticket->agents->firstWhere('pivot.removed_at', null)) 
        // : null;
    }

    /**
     * Get the timestamp of the most recent message in the ticket.
     *
     * @param TicketEntity $ticket
     * @return int|null
     */
    private function getLastMessageTimestamp(TicketEntity $ticket): ?int
    {
        // Load the messages relationship if not already loaded
        if (!$ticket->relationLoaded('messages')) {
            $ticket->load([
                'messages' => function ($query) {
                    $query->orderBy('created_at', 'desc')->limit(1);
                }
            ]);
        }

        // Get the latest message
        $lastMessage = $ticket->messages->first();

        // Return the timestamp if a message exists, otherwise null
        return $lastMessage
            ? $lastMessage->created_at->timestamp
            : null;
    }

    /**
     * Get the count of messages in the ticket.
     *
     * @param TicketEntity $ticket
     * @return int
     */
    private function getMessageCount(TicketEntity $ticket): int
    {
        if ($ticket->relationLoaded('messages')) {
            return $ticket->messages->count();
        }

        return $ticket->messages()->count();
    }

}