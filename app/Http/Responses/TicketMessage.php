<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\User;
use Illuminate\Support\Carbon;

class TicketMessage extends DataInterface
{
    /**
     * Message ID.
     *
     * @var string
     */
    public string $id;

    /**
     * Ticket ID.
     *
     * @var string
     */
    public string $ticket_id;

     /**
     * Message type (message, private_note, activity_log).
     *
     * @var string
     */
    public string $message_type;

    /**
     * Sender type.
     *
     * @var string
     */
    public string $sender_type;

    /**
     * Sender ID.
     *
     * @var string|null
     */
    public ?string $sender_id;

    /**
     * Sender information.
     *
     * @var array|null
     */
    public ?array $sender;

     /**
     * Whether the message is private.
     *
     * @var bool
     */
    public bool $is_private;

     /**
     * Message creation timestamp.
     *
     * @var string
     */
    public string $created_at;

    /**
     * Message update timestamp.
     *
     * @var string
     */
    public string $updated_at;

    /**
     * Message content.
     *
     * @var string|null
     */
    public ?string $content;

    /**
     * Attachments for the message.
     *
     * @var array
     */
    public array $attachments = [];

    /**
     * Create a new TicketMessage response instance.
     *
     * @param \App\Models\TicketMessage $ticketMessage
     */
    public function __construct(\App\Models\TicketMessage $ticketMessage)
    {
        $this->id = $ticketMessage->id;
        $this->ticket_id = $ticketMessage->ticket_id;
        $this->message_type = $ticketMessage->message_type ?? 'message';
        $this->sender_type = $ticketMessage->sender_type;
        $this->sender_id = $ticketMessage->sender_id;
        $this->is_private = $ticketMessage->is_private ?? false;
        $this->created_at = Carbon::parse($ticketMessage->created_at)->toIso8601String();
        $this->updated_at = Carbon::parse($ticketMessage->updated_at)->toIso8601String();

        // Handle content based on message type and messageable relationship
        if ($ticketMessage->relationLoaded('messageable') && $ticketMessage->messageable) {
            // Get content from the messageable relationship (polymorphic)
            $this->content = $this->getMessageableContent($ticketMessage);
        } else {
            // Fallback to direct content
            $this->content = $ticketMessage->content;
        }

        // Parse sender information based on sender type
        $this->sender = $this->parseSender($ticketMessage);

        // Add attachments if loaded
        // if ($ticketMessage->relationLoaded('attachments')) {
        //     $this->attachments = $this->parseAttachments($ticketMessage->attachments);
        // }
    }

    /**
     * Get content from messageable relationship based on its type.
     *
     * @param \App\Models\TicketMessage $ticketMessage
     * @return string|null
     */
    private function getMessageableContent(\App\Models\TicketMessage $ticketMessage): ?string
    {
        $messageable = $ticketMessage->messageable;
        
        if (!$messageable) {
            return $ticketMessage->content;
        }

        // Different handling based on messageable type
        $messageableClass = get_class($messageable);
        
        switch ($messageableClass) {
            case 'App\Models\TicketTextMessage':
                return $messageable->content;
            case 'App\Models\TicketFileMessage':
                return $messageable->caption ?? "File: {$messageable->file_name}";
            default:
                // For any other types, try to access content property or return a default
                return $messageable->content ?? $ticketMessage->content ?? null;
        }
    }

    /**
     * Parse sender information based on sender type.
     *
     * @param \App\Models\TicketMessage $ticketMessage
     * @return array|null
     */
    private function parseSender(\App\Models\TicketMessage $ticketMessage): ?array
    {
        if($ticketMessage->id == "9eac142f-6597-4cdb-b609-72e0c9c3684b"){
            // dd($ticketMessage->sender);

        }
        if (!$ticketMessage->sender) {
            return null;
        }

        // Different handling based on sender type
        switch ($ticketMessage->sender_type) {
            case User::class:
              
                return [
                    'id' => $ticketMessage->sender->id,
                    'name' => $ticketMessage->sender->name ?? $ticketMessage->sender->username ?? 'Support Agent',
                    'email' => $ticketMessage->sender->email ?? null,
                    'type' => 'agent',
                    'avatar' => $this->getAvatarUrl($ticketMessage->sender),
                ];
            
            case ContactEntity::class:
                // For customer contacts
                $name = $this->getContactName($ticketMessage->sender);
                $email = $this->getContactEmail($ticketMessage->sender);
                return [
                    'id' => $ticketMessage->sender->id,
                    'name' => $name,
                    'email' => $this->getContactEmail($ticketMessage->sender),
                    'type' => 'customer',
                    'avatar' => null, // Can generate initials-based avatar on frontend
                ];
            
            default:
                // Generic handling for any other sender type
                return [
                    'id' => $ticketMessage->sender->id,
                    'name' => $ticketMessage->sender->name ?? 'Unknown Sender',
                    'type' => 'system',
                ];
        }
    }

    /**
     * Get the contact's name from their attributes or identifiers.
     *
     * @param ContactEntity $contact
     * @return string
     */
    private function getContactName(ContactEntity $contact): string
    {
        $contactName = $contact->getNameIdentifier(Channel::TICKETING_PLATFORM);
        if($contactName){
            return $contactName;
        }
        $contactEmail = $contact->getEmailIdentifier();
        
        if($contactEmail){
            return $contactEmail;
        }

        $contactPhone = $contact->getPhoneIdentifier();
        if($contactPhone){
            return $contactPhone;
        }
        // Fallback
        return 'Customer';
    }

    /**
     * Get the contact's email from their identifiers.
     *
     * @param ContactEntity $contact
     * @return string|null
     */
    private function getContactEmail(ContactEntity $contact): ?string
    {
        $contactEmail = $contact->getEmailIdentifier();
        if($contactEmail){
            return $contactEmail;
        }
        
        return null;
    }

    /**
     * Get avatar URL for a user.
     *
     * @param User $user
     * @return string|null
     */
    private function getAvatarUrl($user): ?string
    {
        // Implement based on your user avatar implementation
        // This is a placeholder - replace with your actual avatar logic
        if (method_exists($user, 'getAvatarUrl')) {
            return $user->getAvatarUrl();
        }
        
        return null;
    }

    /**
     * Parse attachments into a standardized format.
     *
     * @param \Illuminate\Database\Eloquent\Collection $attachments
     * @return array
     */
    private function parseAttachments($attachments): array
    {
        if (!$attachments || $attachments->isEmpty()) {
            return [];
        }

        return $attachments->map(function ($attachment) {
            return [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'file_size' => $attachment->file_size,
                'mime_type' => $attachment->mime_type,
                'preview_url' => $attachment->getPreviewUrl(),
                'download_url' => $attachment->getDownloadUrl(),
                'is_image' => $attachment->isImage(),
            ];
        })->toArray();
    }
}