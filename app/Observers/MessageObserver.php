<?php

namespace App\Observers;

use App\Models\Conversation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MessageObserver
{
    /**
     * Handle the message "created" event.
     * Updates the conversation's last_message_at timestamp for faster sorting.
     */
    public function created(Model $message): void
    {
        $this->updateConversationLastMessageAt($message);
    }

     public function updated(Model $message): void
    {
        $this->updateConversationLastMessageAt($message);
    }

    /**
     * Update the conversation's last_message_at field
     */
    private function updateConversationLastMessageAt(Model $message): void
    {
        // Check if conversation_id exists on the message
        if (empty($message->conversation_id)) {
            return;
        }

        // Check if last_message_at column exists
        if (!Schema::hasColumn('conversations', 'last_message_at')) {
            return;
        }

        try {
            // Use direct update for better performance
            Conversation::where('id', $message->conversation_id)
                ->update(['last_message_at' => now()]);
        } catch (\Exception $e) {
            Log::error('MessageObserver: Failed to update last_message_at', [
                'conversation_id' => $message->conversation_id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
