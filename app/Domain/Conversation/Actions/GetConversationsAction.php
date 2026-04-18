<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\DTOs\ConversationFilterDTO;
use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Http\Responses\Conversation as ConversationResponse;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class GetConversationsAction
{
    public function __construct(
        private ConversationRepositoryInterface $repository
    ) {}

    public function execute(Request $request, Workspace $workspace, $accessor): JsonResponse
    {
        // Check authorization
        if (!$this->canAccessWorkspaceConversations($accessor, $workspace)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view conversations in this workspace.',
                'data' => null,
            ], 403);
        }

        $filters = ConversationFilterDTO::fromRequest($request);

        // Get paginated conversations
        $paginated = $this->repository->getAll($workspace, $filters, $accessor);

        // Transform the collection
        $collection = $paginated->getCollection()->map(
            fn($conversation) => new ConversationResponse($conversation)
        );
        $paginated->setCollection($collection);

        // Calculate statistics only on first page
        $statistics = $filters->page == 1
            ? $this->repository->getStatistics($workspace, $accessor, $filters->search)->toArray()
            : [];

        return $this->paginateResponse($paginated, ['statistics' => $statistics]);
    }

    private function canAccessWorkspaceConversations($accessor, Workspace $workspace): bool
    {
        return $accessor->isOrganizationOwner($workspace->organization)
            || $accessor instanceof \App\Models\AccessKey
            || $accessor->isMemberOfWorkspace($workspace);
    }

    private function paginateResponse(LengthAwarePaginator $items, array $additional = []): JsonResponse
    {
        $response = [
            'data' => $items->items(),
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ];

        return response()->json(array_merge($response, $additional));
    }
}
