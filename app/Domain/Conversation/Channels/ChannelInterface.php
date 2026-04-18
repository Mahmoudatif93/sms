<?php

namespace App\Domain\Conversation\Channels;

use App\Domain\Conversation\DTOs\MessageResultDTO;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

interface ChannelInterface
{
    /**
     * Get the platform identifier for this channel
     */
    public function getPlatform(): string;

    /**
     * Send a message through this channel
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse;

    /**
     * Send a text message
     */
    public function sendTextMessage(Request $request, Conversation $conversation): JsonResponse;

    /**
     * Send a file/media message
     */
    public function sendFileMessage(Request $request, Conversation $conversation): JsonResponse;

    /**
     * Send a reaction message
     */
    public function sendReactionMessage(Request $request, Conversation $conversation): JsonResponse;

    /**
     * Mark messages as read
     */
    public function markAsRead(Conversation $conversation): JsonResponse;

    /**
     * Mark messages as delivered
     */
    public function markAsDelivered(Conversation $conversation): JsonResponse;

    /**
     * Check if channel supports a specific message type
     */
    public function supportsMessageType(string $type): bool;

    /**
     * Get supported message types for this channel
     */
    public function getSupportedMessageTypes(): array;

    /**
     * Handle conversation close event
     */
    public function handleClose(Conversation $conversation, string $closedBy): void;

    /**
     * Handle conversation reopen event
     */
    public function handleReopen(Conversation $conversation): void;
}
