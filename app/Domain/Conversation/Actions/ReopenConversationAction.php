<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Events\ConversationReopened;
use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Domain\Conversation\Services\ChannelResolver;
use App\Http\Responses\ConversationDetails;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ReopenConversationAction
{
    use ResponseManager;

    public function __construct(
        private ConversationRepositoryInterface $repository,
        private ChannelResolver $channelResolver
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

            // Check if conversation is already open/active
            if ($conversation->status !== Conversation::STATUS_CLOSED
                && $conversation->status !== Conversation::STATUS_ARCHIVED) {
                return $this->response(
                    success: false,
                    message: "Conversation is already active.",
                    statusCode: 400
                );
            }

            // Update conversation status to active
            $this->repository->updateStatus($conversation, Conversation::STATUS_ACTIVE);

            // Add system note
            $conversation->notes()->create([
                'user_id' => Auth::id(),
                'content' => 'Conversation reopened by ' . Auth::user()->name,
                'is_system_note' => true
            ]);

            // Handle platform-specific reopen actions
            $channel = $conversation->channel;
            if ($this->channelResolver->supports($channel->platform)) {
                $channelImpl = $this->channelResolver->resolve($channel->platform);
                $channelImpl->handleReopen($conversation);
            }

            event(new ConversationReopened($conversation, Auth::user()->name));

            return $this->response(
                true,
                "Conversation reopened successfully",
                new ConversationDetails($conversation)
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
