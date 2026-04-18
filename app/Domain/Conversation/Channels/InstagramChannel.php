<?php

namespace App\Domain\Conversation\Channels;

use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instagram Channel - Placeholder for future implementation
 */
class InstagramChannel extends AbstractChannel
{
    public const PLATFORM = 'instagram';

    protected array $supportedMessageTypes = ['text', 'image', 'video', 'story_reply'];

    public function getPlatform(): string
    {
        return self::PLATFORM;
    }

    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function sendImageMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function sendVideoMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function markAsRead(Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    public function markAsDelivered(Conversation $conversation): JsonResponse
    {
        return $this->notImplementedResponse();
    }

    private function notImplementedResponse(): JsonResponse
    {
        return $this->errorResponse(
            'Instagram channel is not yet implemented. Coming soon!',
            ['status' => 'not_implemented'],
            501
        );
    }
}
