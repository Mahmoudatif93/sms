<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\MetaConversationLog;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetaConversationLogController extends BaseApiController
{
    public function index(Workspace $workspace, Conversation $conversation): JsonResponse
    {
        $logs = MetaConversationLog::
            orderByDesc('created_at')
            ->get([
                'id',
                'text_log'
            ]);

        return response()->json([
            'data' => $logs
        ]);
    }
}
