<?php

namespace App\Domain\Conversation\Actions;

use App\Http\Responses\Conversation as ConversationResponse;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SwitchWorkspaceAction
{
    use ResponseManager;

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $newWorkspace = Workspace::findOrFail($request->input('new_workspace_id'));

            // 1. Ensure the new workspace belongs to the same organization
            if ($workspace->organization_id !== $newWorkspace->organization_id) {
                return $this->response(
                    false,
                    "Target workspace must belong to the same organization.",
                    null,
                    403
                );
            }

            // 2. Ensure the conversation's channel is available in the target workspace
            $channel = $conversation->channel;
            if (!$newWorkspace->channels()->where('channels.id', $channel->id)->exists()) {
                return $this->response(
                    false,
                    "Channel not available in the target workspace.",
                    null,
                    422
                );
            }

            // 3. Switch conversation workspace
            $conversation->workspace_id = $newWorkspace->id;
            $conversation->save();

            $authenticatedUser = auth('api')->user();
            $user = User::find($authenticatedUser->getAuthIdentifier());

            // Add system note
            $conversation->notes()->create([
                'user_id' => $user->id,
                'content' => "Conversation moved to workspace {$newWorkspace->name} by " . $user->name,
                'is_system_note' => true,
            ]);

            return $this->response(
                true,
                "Conversation moved successfully.",
                new ConversationResponse($conversation)
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'Failed to switch workspace: ' . $e->getMessage(), null, 500);
        }
    }
}
