<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Chatbot\Actions\Import\ImportJsonKnowledgeAction;
use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Domain\Chatbot\Requests\ImportKnowledgeRequest;
use App\Domain\Chatbot\Requests\StoreChatbotSettingsRequest;
use App\Domain\Chatbot\Requests\StoreKnowledgeItemRequest;
use App\Http\Controllers\BaseApiController;
use App\Models\Channel;
use App\Models\ChatbotKnowledgeBase;
use App\Models\Workspace;
use App\Traits\ResponseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends BaseApiController
{
    use ResponseManager;

    public function __construct(
        private ChatbotRepositoryInterface $repository
    ) {}

    // ========================================
    // Settings
    // ========================================

    public function getSettings(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $settings = $this->repository->getSettings($channel->id);

        if (!$settings) {
            return $this->successResponse('No settings found', [
                'is_enabled' => false,
                'channel_id' => $channel->id,
            ]);
        }

        return $this->successResponse('Settings retrieved', $settings);
    }

    public function updateSettings(StoreChatbotSettingsRequest $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $settings = $this->repository->createOrUpdateSettings(
            $channel->id,
            $request->validated()
        );

        return $this->successResponse('Settings updated successfully', $settings);
    }

    // ========================================
    // Knowledge Base
    // ========================================

    public function listKnowledge(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', false);
        $knowledge = $this->repository->getKnowledge($channel->id, $activeOnly);

        return $this->successResponse('Knowledge base retrieved', [
            'items' => $knowledge,
            'total' => $knowledge->count(),
        ]);
    }

    public function storeKnowledge(StoreKnowledgeItemRequest $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $data = $request->validated();
        $data['channel_id'] = $channel->id;

        $knowledge = $this->repository->createKnowledge($data);

        return $this->successResponse('Knowledge item created', $knowledge, 201);
    }

    public function showKnowledge(Request $request, Workspace $workspace, Channel $channel, ChatbotKnowledgeBase $item): JsonResponse
    {
        if ($item->channel_id !== $channel->id) {
            return $this->errorResponse('Knowledge item not found', null, 404);
        }

        return $this->successResponse('Knowledge item retrieved', $item);
    }

    public function updateKnowledge(StoreKnowledgeItemRequest $request, Workspace $workspace, Channel $channel, ChatbotKnowledgeBase $item): JsonResponse
    {
        if ($item->channel_id !== $channel->id) {
            return $this->errorResponse('Knowledge item not found', null, 404);
        }

        $knowledge = $this->repository->updateKnowledge($item->id, $request->validated());

        return $this->successResponse('Knowledge item updated', $knowledge);
    }

    public function deleteKnowledge(Request $request, Workspace $workspace, Channel $channel, ChatbotKnowledgeBase $item): JsonResponse
    {
        if ($item->channel_id !== $channel->id) {
            return $this->errorResponse('Knowledge item not found', null, 404);
        }

        $this->repository->deleteKnowledge($item->id);

        return $this->successResponse('Knowledge item deleted');
    }

    // ========================================
    // Import/Export
    // ========================================

    public function importJson(ImportKnowledgeRequest $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $data = $request->input('data');

        // If file uploaded, parse it
        if ($request->hasFile('file')) {
            $content = file_get_contents($request->file('file')->getRealPath());
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->errorResponse('Invalid JSON file', null, 400);
            }
        }

        $action = app(ImportJsonKnowledgeAction::class);
        $result = $action->execute($channel, $data);

        if (!$result->isSuccessful() && $result->imported === 0) {
            return $this->errorResponse('Import failed', $result->toArray(), 400);
        }

        return $this->successResponse('Import completed', $result->toArray());
    }

    public function exportKnowledge(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $knowledge = $this->repository->getKnowledge($channel->id, false);

        $exportData = [
            'knowledge' => $knowledge->map(function ($item) {
                return [
                    'intent' => $item->intent,
                    'category' => $item->category,
                    'keywords' => $item->keywords,
                    'question_ar' => $item->question_ar,
                    'question_en' => $item->question_en,
                    'answer_ar' => $item->answer_ar,
                    'answer_en' => $item->answer_en,
                    'may_need_handoff' => $item->may_need_handoff,
                    'requires_handoff' => $item->requires_handoff,
                    'priority' => $item->priority,
                ];
            })->values()->toArray(),
        ];

        return response()->json($exportData)
            ->header('Content-Disposition', 'attachment; filename="chatbot_knowledge_' . $channel->id . '.json"');
    }

    // ========================================
    // Statistics
    // ========================================

    public function getStats(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $settings = $this->repository->getSettings($channel->id);
        $knowledge = $this->repository->getKnowledge($channel->id, false);

        $stats = [
            'is_enabled' => $settings?->is_enabled ?? false,
            'knowledge_items' => [
                'total' => $knowledge->count(),
                'active' => $knowledge->where('is_active', true)->count(),
                'inactive' => $knowledge->where('is_active', false)->count(),
            ],
            'categories' => $knowledge->pluck('category')->filter()->unique()->values(),
            'settings' => [
                'ai_model' => $settings?->ai_model ?? 'gpt-4o-mini',
                'handoff_threshold' => $settings?->handoff_threshold ?? 2,
            ],
        ];

        return $this->successResponse('Stats retrieved', $stats);
    }

    // ========================================
    // Toggle Bot
    // ========================================

    public function toggleBot(Request $request, Workspace $workspace, Channel $channel): JsonResponse
    {
        $settings = $this->repository->getSettings($channel->id);

        $newState = !($settings?->is_enabled ?? false);

        $settings = $this->repository->createOrUpdateSettings($channel->id, [
            'is_enabled' => $newState,
        ]);

        $message = $newState ? 'Chatbot enabled' : 'Chatbot disabled';

        return $this->successResponse($message, $settings);
    }
}
