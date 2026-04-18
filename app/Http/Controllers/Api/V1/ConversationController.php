<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Conversation\Actions\AI\ImproveWritingAction;
use App\Domain\Conversation\Actions\AI\SuggestReplyAction;
use App\Domain\Conversation\Actions\AI\SummarizeAction;
use App\Domain\Conversation\Requests\AddNoteRequest;
use App\Domain\Conversation\Requests\ListConversationsRequest;
use App\Domain\Conversation\Requests\SendMessageRequest;
use App\Domain\Conversation\Requests\StartConversationRequest;
use App\Domain\Conversation\Requests\SwitchWorkspaceRequest;
use App\Domain\Conversation\Services\ConversationService;
use App\Http\Controllers\BaseApiController;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Thin Controller - delegates all logic to ConversationService
 * Total: ~120 lines (under 150 limit)
 */
class ConversationController extends BaseApiController
{
    use ResponseManager;

    public function __construct(
        private ConversationService $conversationService
    ) {}

    public function index(ListConversationsRequest $request, Workspace $workspace): JsonResponse
    {
        return $this->conversationService->getAllConversations($request, $workspace, $this->getAccessor($request));
    }

    public function show(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->getConversation($request, $workspace, $conversation);
    }

    public function store(StartConversationRequest $request, Workspace $workspace): JsonResponse
    {
        return $this->conversationService->startConversation($request, $workspace);
    }

    public function sendMessage(SendMessageRequest $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->sendMessage($request, $conversation);
    }

    public function markAsRead(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->markAsRead($workspace, $conversation);
    }

    public function markAsDelivered(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->markAsDelivered($workspace, $conversation);
    }

    public function close(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->closeConversation($workspace, $conversation);
    }

    public function reopen(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->reopenConversation($workspace, $conversation);
    }

    public function assignAgent(Request $request, Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        return $this->conversationService->assignAgent($workspace, $conversation, $user);
    }

    public function removeAgent(Request $request, Workspace $workspace, Conversation $conversation, User $user): JsonResponse
    {
        return $this->conversationService->removeAgent($workspace, $conversation, $user);
    }

    public function addNote(AddNoteRequest $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->addNote($request, $workspace, $conversation);
    }

    public function getNotes(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->getNotes($workspace, $conversation);
    }

    public function switchWorkspace(SwitchWorkspaceRequest $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return $this->conversationService->switchWorkspace($request, $workspace, $conversation);
    }

    public function stats(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->conversationService->getStats($request, $workspace, $this->getAccessor($request));
    }

    // AI Features
    public function aiSuggestReply(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return app(SuggestReplyAction::class)->execute($request, $workspace, $conversation);
    }

    public function aiImproveWriting(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $request->validate(['text' => 'required|string|max:5000']);
        return app(ImproveWritingAction::class)->execute($request, $workspace, $conversation);
    }

    public function aiSummarize(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        return app(SummarizeAction::class)->execute($request, $workspace, $conversation);
    }
}
