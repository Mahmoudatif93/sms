<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Events\AgentRemoved;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ConversationManager;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;

class RemoveAgentAction
{
    use ResponseManager, ConversationManager;

    public function execute(Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }

        $removed = $this->removeInboxAgentFromConversation($user, $conversation);

        if (!$removed) {
            return $this->response(false, "Agent not currently assigned.", null, 409);
        }

        event(new AgentRemoved($conversation, $user, auth('api')->user()?->name));

        return $this->response(true, "Agent removed successfully.");
    }
}
