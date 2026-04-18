<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\DTOs\ConversationNoteDTO;
use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Http\Responses\ConversationNote;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddNoteAction
{
    use ResponseManager;

    public function __construct(
        private ConversationRepositoryInterface $repository
    ) {}

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            // Ensure the conversation belongs to a channel inside the workspace
            if (!$this->repository->belongsToWorkspace($conversation, $workspace)) {
                return $this->response(
                    success: false,
                    message: "Conversation not found in this workspace.",
                    statusCode: 404
                );
            }

            $authenticatedUser = auth('api')->user();
            $user = User::find($authenticatedUser->getAuthIdentifier());

            $dto = ConversationNoteDTO::fromRequest($request, $user->id, $conversation->id);

            // Create the note
            $note = $conversation->notes()->create($dto->toArray());

            // Load the user relationship
            $note->load('user');

            return $this->response(
                true,
                "Note added successfully",
                new ConversationNote($note)
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
