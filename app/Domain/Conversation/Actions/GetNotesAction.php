<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Http\Responses\ConversationNote;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;

class GetNotesAction
{
    use ResponseManager;

    public function __construct(
        private ConversationRepositoryInterface $repository
    ) {}

    public function execute(Workspace $workspace, Conversation $conversation): JsonResponse
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

            // Fetch notes with user information
            $notes = $conversation->notes()->with('user')->latest()->get();

            return $this->response(
                true,
                "Conversation notes retrieved successfully",
                $notes->map(fn($note) => new ConversationNote($note))
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
