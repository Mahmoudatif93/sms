<?php

namespace App\Domain\Conversation\Actions\AI;

use App\Models\Conversation;
use App\Models\Workspace;
use App\Traits\ConversationAIFeatures;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImproveWritingAction
{
    use ResponseManager, ConversationAIFeatures;

    public function execute(Request $request, Workspace $workspace, Conversation $conversation): JsonResponse
    {
        try {
            $lang = $request->input('lang', 'auto');
            $text = $request->input('text');
            $improved = $this->improveWriting($workspace, $text, $lang);

            return $this->response(true, "AI improvement generated", [
                'improved_text' => $improved,
            ]);
        } catch (\Throwable $e) {
            return $this->response(false, "Failed to improve writing", ['error' => $e->getMessage()], 500);
        }
    }
}
