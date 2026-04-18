<?php

namespace App\Domain\Conversation\Repositories;

use App\Domain\Conversation\DTOs\ConversationFilterDTO;
use App\Domain\Conversation\DTOs\ConversationStatsDTO;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Pagination\LengthAwarePaginator;

interface ConversationRepositoryInterface
{
    /**
     * Get all conversations with filters
     */
    public function getAll(Workspace $workspace, ConversationFilterDTO $filters, $accessor): LengthAwarePaginator;

    /**
     * Find conversation by ID
     */
    public function findById(string $id): ?Conversation;

    /**
     * Find conversation by ID within a workspace
     */
    public function findByIdInWorkspace(string $id, Workspace $workspace): ?Conversation;

    /**
     * Create a new conversation
     */
    public function create(array $data): Conversation;

    /**
     * Update a conversation
     */
    public function update(Conversation $conversation, array $data): Conversation;

    /**
     * Update conversation status
     */
    public function updateStatus(Conversation $conversation, string $status): Conversation;

    /**
     * Get conversation statistics
     */
    public function getStatistics(Workspace $workspace, $accessor, ?string $search = null): ConversationStatsDTO;

    /**
     * Get active conversation for contact
     */
    public function getActiveConversation(string $contactId, string $platform, string $channelId): ?Conversation;

    /**
     * Check if conversation belongs to workspace
     */
    public function belongsToWorkspace(Conversation $conversation, Workspace $workspace): bool;
}
