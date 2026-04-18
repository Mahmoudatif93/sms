<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Events\AgentAssigned;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ConversationManager;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;

class AssignAgentAction
{
    use ResponseManager, ConversationManager;

    public function execute(Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }

        $assigned = $this->assignInboxAgentToConversation($user, $conversation);

        if (!$assigned) {
            return $this->response(false, "Agent is already assigned.", null, 409);
        }

        event(new AgentAssigned($conversation, $user, auth('api')->user()?->name));

        return $this->response(true, "Agent assigned successfully.");
    }
}
