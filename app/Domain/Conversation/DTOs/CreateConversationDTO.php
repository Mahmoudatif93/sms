<?php

namespace App\Domain\Conversation\DTOs;

use Illuminate\Http\Request;

final readonly class CreateConversationDTO
{
    public function __construct(
        public string $platform,
        public string $channelId,
        public string $contactId,
        public string $workspaceId,
        public ?string $message = null,
        public ?string $inboxAgentId = null,
    ) {}

    public static function fromRequest(Request $request, string $workspaceId): self
    {
        return new self(
            platform: $request->input('platform'),
            channelId: $request->input('channel_id'),
            contactId: $request->input('contact_id'),
            workspaceId: $workspaceId,
            message: $request->input('message'),
            inboxAgentId: $request->input('inbox_agent_id'),
        );
    }

    public function toArray(): array
    {
        return [
            'platform' => $this->platform,
            'channel_id' => $this->channelId,
            'contact_id' => $this->contactId,
            'workspace_id' => $this->workspaceId,
            'message' => $this->message,
            'inbox_agent_id' => $this->inboxAgentId,
        ];
    }
}
