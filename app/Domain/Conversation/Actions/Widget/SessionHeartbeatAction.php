<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Models\Conversation;

class SessionHeartbeatAction
{
    public function execute(string $sessionId): array
    {
        $conversation = Conversation::findOrFail($sessionId);

        if ($conversation->status === Conversation::STATUS_ENDED) {
            throw new \Exception('Conversation has ended');
        }

        $conversation->touch();

        return [
            'session' => [
                'id' => $conversation->id,
                'status' => $conversation->status,
            ],
        ];
    }
}
