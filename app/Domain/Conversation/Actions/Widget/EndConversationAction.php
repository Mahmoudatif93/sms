<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Models\Conversation;

class EndConversationAction
{
    public function execute(string $sessionId): array
    {
        $conversation = Conversation::findOrFail($sessionId);

        // Only allow ending active or waiting conversations
        $validStatuses = [
            Conversation::STATUS_ACTIVE,
            Conversation::STATUS_WAITING,
        ];

        if (!in_array($conversation->status, $validStatuses)) {
            throw new \Exception('Cannot end a conversation that is not active or waiting');
        }

        $conversation->update([
            'status' => Conversation::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        return [
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
        ];
    }
}
