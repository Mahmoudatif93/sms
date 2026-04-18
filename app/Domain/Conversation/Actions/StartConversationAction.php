<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\DTOs\CreateConversationDTO;
use App\Domain\Conversation\Events\ConversationStarted;
use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Workspace;
use App\Traits\ConversationManager;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StartConversationAction
{
    use ResponseManager, ConversationManager;

    public function execute(Request $request, Workspace $workspace): JsonResponse
    {
        $dto = CreateConversationDTO::fromRequest($request, $workspace->id);

        $contact = ContactEntity::findOrFail($dto->contactId);
        $channel = Channel::findOrFail($dto->channelId);

        try {
            $conversation = $this->startConversation(
                platform: $dto->platform,
                channel: $channel,
                contact: $contact,
                message: $dto->message,
                workspaceId: $workspace->id
            );

            event(new ConversationStarted($conversation, auth('api')->id()));

            return $this->response(true, 'Conversation started successfully.', $conversation);
        } catch (\Throwable $e) {
            return $this->response(false, 'Failed to start conversation.', ['error' => $e->getMessage()], 500);
        }
    }
}
