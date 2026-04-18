<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Http\Responses\ConversationDetails;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetConversationAction
{
    use ResponseManager;

    public function __construct(
        private ConversationRepositoryInterface $repository
    ) {}

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        // Ensure the conversation belongs to a channel inside the workspace
        if (!$this->repository->belongsToWorkspace($conversation, $workspace)) {
            return $this->response(
                success: false,
                message: "Conversation not found in this workspace.",
                statusCode: 404
            );
        }

        return $this->response(
            message: "Conversation Retrieved Successfully",
            data: new ConversationDetails(
                $conversation,
                [
                    'lang' => $request->input('lang', "en"),
                    'translate' => $request->input('translate', false),
                    'last_message_id' => $request->input('id', null),
                    'limit' => $request->input('limit', 15),
                ]
            )
        );
    }
}
