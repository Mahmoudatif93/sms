<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Events\ConversationClosed;
use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Domain\Conversation\Services\ChannelResolver;
use App\Http\Responses\ConversationDetails;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;

class CloseConversationAction
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

            // Check if conversation is already closed/archived
            if ($conversation->status === Conversation::STATUS_ARCHIVED) {
                return $this->response(
                    success: false,
                    message: "Conversation is already closed.",
                    statusCode: 400
                );
            }

            // Update conversation status to archived
            $this->repository->updateStatus($conversation, Conversation::STATUS_ARCHIVED);

            // Add system note
            $conversation->notes()->create([
                'user_id' => auth('api')->user()->id,
                'content' => 'Conversation closed by ' . auth('api')->user()->name,
                'is_system_note' => true
            ]);

            // Handle platform-specific close actions
            $channel = $conversation->channel;
            if ($this->channelResolver->supports($channel->platform)) {
                $channelImpl = $this->channelResolver->resolve($channel->platform);
                $channelImpl->handleClose($conversation, 'agent');
            }

            event(new ConversationClosed($conversation, auth('api')->user()->name));

            return $this->response(
                true,
                "Conversation closed successfully",
                new ConversationDetails($conversation)
            );
        } catch (\Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
