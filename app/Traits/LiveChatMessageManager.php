<?php

namespace App\Traits;
use App\Models\LiveChatMessageStatus;


trait LiveChatMessageManager
{

     /**
     * Helper Method to Save Message Status
     */
    private function saveMessageStatus(string $messageId, string $status, int $timestamp = null): void
    {


        LiveChatMessageStatus::updateOrCreate(
            [
                'livechat_message_id' => $messageId,
                'status' => $status,
            ],
            [
                'timestamp' => empty($timestamp) ? time() : $timestamp,
            ]
        );
    }
}
