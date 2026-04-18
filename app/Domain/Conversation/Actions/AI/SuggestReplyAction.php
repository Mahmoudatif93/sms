<?php

namespace App\Domain\Conversation\Actions\AI;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ConversationAIFeatures;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestReplyAction
{
    use ResponseManager, ConversationAIFeatures;

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $lang = $request->input('lang', 'auto');
            $suggestion = $this->suggestReply($conversation, $lang);

            return $this->response(true, "AI suggestion generated", [
                'suggestion' => $suggestion,
            ]);
        } catch (\Throwable $e) {
            return $this->response(false, "Failed to suggest reply", ['error' => $e->getMessage()], 500);
        }
    }
}
