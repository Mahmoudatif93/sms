<?php

namespace App\Domain\Conversation\Actions;

use App\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetStatsAction
{
    use ResponseManager;

    public function __construct(
        private ConversationRepositoryInterface $repository
    ) {}

    public function execute(Request $request, Workspace $workspace, $accessor): JsonResponse
    {
        $search = $request->get('search');

        // Remove leading zero if search starts with 0
        if ($search && str_starts_with($search, '0')) {
            $search = ltrim($search, '0');
        }

        // Check authorization
        if (!$this->canAccessWorkspaceConversations($accessor, $workspace)) {
            return $this->errorResponse("Unauthorized to view conversations in this workspace.", null, 403);
        }

        // Calculate and return statistics
        $statistics = $this->repository->getStatistics($workspace, $accessor, $search);

        return $this->response(true, 'Conversation statistics retrieved successfully', $statistics->toArray());
    }

    private function canAccessWorkspaceConversations($accessor, Workspace $workspace): bool
    {
        return $accessor->isOrganizationOwner($workspace->organization)
            || $accessor instanceof \App\Models\AccessKey
            || $accessor->isMemberOfWorkspace($workspace);
    }
}
