<?php

namespace App\Domain\Conversation\Services;

use App\Domain\Conversation\Actions\AddNoteAction;
use App\Domain\Conversation\Actions\AssignAgentAction;
use App\Domain\Conversation\Actions\CloseConversationAction;
use App\Domain\Conversation\Actions\GetConversationAction;
use App\Domain\Conversation\Actions\GetConversationsAction;
use App\Domain\Conversation\Actions\GetNotesAction;
use App\Domain\Conversation\Actions\GetStatsAction;
use App\Domain\Conversation\Actions\MarkAsDeliveredAction;
use App\Domain\Conversation\Actions\MarkAsReadAction;
use App\Domain\Conversation\Actions\RemoveAgentAction;
use App\Domain\Conversation\Actions\ReopenConversationAction;
use App\Domain\Conversation\Actions\SendMessageAction;
use App\Domain\Conversation\Actions\StartConversationAction;
use App\Domain\Conversation\Actions\SwitchWorkspaceAction;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ConversationService - Main orchestrator for conversation operations
 *
 * This service delegates to specific Actions for business logic.
 * It provides a unified interface for the Controller.
 */
class ConversationService
{
    public function __construct(
        private GetConversationsAction $getConversationsAction,
        private GetConversationAction $getConversationAction,
        private SendMessageAction $sendMessageAction,
        private StartConversationAction $startConversationAction,
        private CloseConversationAction $closeConversationAction,
        private ReopenConversationAction $reopenConversationAction,
        private AssignAgentAction $assignAgentAction,
        private RemoveAgentAction $removeAgentAction,
        private AddNoteAction $addNoteAction,
        private GetNotesAction $getNotesAction,
        private SwitchWorkspaceAction $switchWorkspaceAction,
        private MarkAsReadAction $markAsReadAction,
        private MarkAsDeliveredAction $markAsDeliveredAction,
        private GetStatsAction $getStatsAction
    ) {}

    public function getAllConversations(Request $request, Workspace $workspace, $accessor): JsonResponse
    {
        return $this->getConversationsAction->execute($request, $workspace, $accessor);
    }

    public function getConversation(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->getConversationAction->execute($request, $workspace, $conversation);
    }

    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        return $this->sendMessageAction->execute($request, $conversation);
    }

    public function startConversation(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->startConversationAction->execute($request, $workspace);
    }

    public function closeConversation(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->closeConversationAction->execute($workspace, $conversation);
    }

    public function reopenConversation(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->reopenConversationAction->execute($workspace, $conversation);
    }

    public function assignAgent(Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        return $this->assignAgentAction->execute($workspace, $conversation, $user);
    }

    public function removeAgent(Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        return $this->removeAgentAction->execute($workspace, $conversation, $user);
    }

    public function addNote(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->addNoteAction->execute($request, $workspace, $conversation);
    }

    public function getNotes(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->getNotesAction->execute($workspace, $conversation);
    }

    public function switchWorkspace(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->switchWorkspaceAction->execute($request, $workspace, $conversation);
    }

    public function markAsRead(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->markAsReadAction->execute($workspace, $conversation);
    }

    public function markAsDelivered(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->markAsDeliveredAction->execute($workspace, $conversation);
    }

    public function getStats(Request $request, Workspace $workspace, $accessor): JsonResponse
    {
        return $this->getStatsAction->execute($request, $workspace, $accessor);
    }
}
