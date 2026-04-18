<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Domain\Conversation\Services\ChannelResolver;
use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;

class MarkAsReadAction
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

            $channel = $conversation->channel;

            // Resolve channel and mark as read
            $channelImplementation = $this->channelResolver->resolve($channel->platform);

            return $channelImplementation->markAsRead($conversation);
        } catch (\Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
