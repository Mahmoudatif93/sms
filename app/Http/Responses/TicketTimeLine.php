<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\TicketActivityLog;
use App\Models\TicketMessage as TicketMessageModel;
use Illuminate\Support\Carbon;

class TicketTimeLine extends DataInterface
{
    /**
     * The ID of the timeline item.
     *
     * @var string
     */
    public string $id;

    /**
     * The type of the timeline item (message or activity_log).
     *
     * @var string
     */
    public string $type;

    /**
     * The message type if it's a message (message, private_note).
     *
     * @var string|null
     */
    public ?string $message_type;

    /**
     * The activity type if it's an activity log.
     *
     * @var string|null
     */
    public ?string $activity_type;

    /**
     * The content or description of the timeline item.
     *
     * @var string
     */
    public string $content;

    /**
     * Whether the item is private or not.
     *
     * @var bool|null
     */
    public ?bool $is_private;

    /**
     * The user who created the timeline item.
     *
     * @var array|null
     */
    public ?array $sender;

    /**
     * Old values for activity logs.
     *
     * @var array|null
     */
    public ?array $old_values;

    /**
     * New values for activity logs.
     *
     * @var array|null
     */
    public ?array $new_values;

    /**
     * Attachments for messages.
     *
     * @var array|null
     */
    public ?array $attachments;

    /**
     * The creation timestamp.
     *
     * @var string
     */
    public string $created_at;

    /**
     * Create a new timeline item instance.
     *
     * @param mixed $item The timeline item (either a TicketMessage, TicketMessage response or TicketActivityLog)
     * @param string $type The type of item ('message' or 'activity_log')
     */
    public function __construct($item, string $type)
    {
        $this->type = $type;
        
        if ($this->type === 'message') {
            $this->constructFromMessage($item);
        } else { // activity_log
            $this->constructFromActivityLog($item);
        }
    }

    /**
     * Construct from a message item.
     *
     * @param mixed $message
     * @return void
     */
    private function constructFromMessage($message): void
    {
        // If it's already a response object, extract its properties
        if ($message instanceof TicketMessage) {
            if($message->id == '9eac142f-6597-4cdb-b609-72e0c9c3684b'){
                // dd($message);

            }
            $this->id = $message->id;
            $this->message_type = $message->message_type;
            $this->content = $message->content ?? '';
            $this->is_private = $message->is_private;
            $this->sender = $message->sender;
            $this->attachments = $message->attachments;
            $this->created_at = $message->created_at;
            $this->activity_type = null;
            $this->old_values = null;
            $this->new_values = null;
            return;
        }
        
        // If it's a model, convert it to a response first
        if ($message instanceof TicketMessageModel) {
            echo 'TicketMessageModel:'. $message->id .'|'; 
            $messageResponse = new TicketMessage($message);
            $this->constructFromMessage($messageResponse);
            return;
        }
        
        // If it's an array (from the database or converted to array)
        $this->id = $message['id'];
        $this->message_type = $message['message_type'] ?? 'message';
        $this->activity_type = null;
        $this->content = $message['content'] ?? '';
        
        // Handle messageable relationship
        if (isset($message['messageable']) && isset($message['messageable']['content'])) {
            $this->content = $message['messageable']['content'];
        }
        
        $this->is_private = $message['is_private'] ?? false;
   
        $this->sender = isset($message['sender']) ? $this->formatUser($message['sender']) : null;
        $this->old_values = null;
        $this->new_values = null;
        $this->attachments = isset($message['attachments']) ? $this->formatAttachments($message['attachments']) : [];
        $this->created_at = Carbon::parse($message['created_at'])->format('Y-m-d H:i:s');
    }

    /**
     * Construct from an activity log item.
     *
     * @param mixed $log
     * @return void
     */
    private function constructFromActivityLog($log): void
    {
        // If it's a model, convert it to an array
        if ($log instanceof TicketActivityLog) {
            $log = $log->toArray();
        }
        
        $this->id = $log['id'];
        $this->message_type = null;
        $this->activity_type = $log['activity_type'] ?? null;
        $this->content = $log['description'] ?? '';
        $this->is_private = false;
        $this->sender = isset($log['user']) ? $this->formatUser($log['user']) : null;
        $this->old_values = $log['old_values'] ?? null;
        $this->new_values = $log['new_values'] ?? null;
        $this->attachments = null;
        $this->created_at = Carbon::parse($log['created_at'])->format('Y-m-d H:i:s');
    }

    /**
     * Format a user object.
     *
     * @param mixed $user
     * @return array
     */
    private function formatUser($user): array
    {
        // If already formatted, return as is
        if (isset($user['type'])) {
            return $user;
        }
        
        return [
            'id' => $user['id'] ?? null,
            'name' => $user['name'] ?? null,
            'email' => $user['email'] ?? null,
            'type' => $user['type'] ?? 'system2',
            'avatar' => $user['avatar'] ?? null,
        ];
    }

    /**
     * Format attachments.
     *
     * @param array $attachments
     * @return array
     */
    private function formatAttachments(array $attachments): array
    {
        // If already formatted with download_url, return as is
        if (!empty($attachments) && isset($attachments[0]['download_url'])) {
            return $attachments;
        }
        
        return array_map(function ($attachment) {
            return [
                'id' => $attachment['id'],
                'file_name' => $attachment['file_name'],
                'file_path' => $attachment['file_path'] ?? null,
                'mime_type' => $attachment['mime_type'],
                'file_size' => $attachment['file_size'],
                'download_url' => isset($attachment['id']) ? 
                    route('tickets.attachments.download', ['id' => $attachment['id']]) : null,
                'preview_url' => $attachment['preview_url'] ?? null,
                'is_image' => $attachment['is_image'] ?? false,
            ];
        }, $attachments);
    }

    /**
     * Create a collection of timeline items.
     *
     * @param array $messages The array of ticket messages (can be models or arrays)
     * @param array $activityLogs The array of activity logs (can be models or arrays)
     * @return array
     */
    public static function createTimeline( $messages,  $activityLogs): array
    {
        // Convert messages to timeline format
        $messageItems = [];
        foreach ($messages as $message) {
            // If it's a model, first convert it to a TicketMessage response
            if ($message instanceof TicketMessageModel) {
                $ticketMessageResponse = new TicketMessage($message);
                $timelineItem = new self($ticketMessageResponse, 'message');
                $messageItems[] = $timelineItem;
            } else {
                // It's an array or already a response object
                $messageItems[] = new self($message, 'message');
            }
        }

        // Convert activity logs to timeline format
        $logItems = [];
        foreach ($activityLogs as $log) {
            // If it's a model, extract to array to avoid double processing
            if ($log instanceof TicketActivityLog) {
                $logArray = [
                    'id' => $log->id,
                    'activity_type' => $log->activity_type,
                    'description' => $log->description,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                        'type' => 'agent'
                    ] : null,
                    'created_at' => $log->created_at,
                ];
                $logItems[] = new self($logArray, 'activity_log');
            } else {
                $logItems[] = new self($log, 'activity_log');
            }
        }

        // Merge and sort by created_at
        $allItems = array_merge($messageItems, $logItems);
        
        usort($allItems, function ($a, $b) {
            return strtotime($a->created_at) - strtotime($b->created_at);
        });

        return $allItems;
    }
}