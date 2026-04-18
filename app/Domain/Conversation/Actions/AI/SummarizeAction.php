<?php

namespace App\Domain\Conversation\Actions\AI;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ConversationAIFeatures;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SummarizeAction
{
    use ResponseManager, ConversationAIFeatures;

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $lang = $request->input('lang', 'auto');
            $summary = $this->summarizeConversation($conversation, $lang);

            return $this->response(true, "Conversation summarized", [
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return $this->response(false, "Failed to summarize conversation", ['error' => $e->getMessage()], 500);
        }
    }
}
