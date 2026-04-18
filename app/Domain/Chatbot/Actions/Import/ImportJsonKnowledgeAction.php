<?php

namespace App\Domain\Chatbot\Actions\Import;

use App\Domain\Chatbot\DTOs\ImportResultDTO;
use App\Domain\Chatbot\Repositories\ChatbotRepositoryInterface;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ImportJsonKnowledgeAction
{
    public function __construct(
        private ChatbotRepositoryInterface $repository
    ) {}

    public function execute(Channel $channel, array $data): ImportResultDTO
    {
        // Validate data structure
        if (!isset($data['knowledge']) || !is_array($data['knowledge'])) {
            return ImportResultDTO::failed(['Invalid data format: missing "knowledge" array']);
        }

        $items = $data['knowledge'];
        $validItems = [];
        $errors = [];

        foreach ($items as $index => $item) {
            $validation = $this->validateItem($item, $index);

            if ($validation['valid']) {
                $validItems[] = $this->normalizeItem($item);
            } else {
                $errors = array_merge($errors, $validation['errors']);
            }
        }

        if (empty($validItems)) {
            return ImportResultDTO::failed($errors);
        }

        try {
            $result = $this->repository->importKnowledge($channel->id, $validItems);

            return ImportResultDTO::partial(
                $result['imported'],
                $result['updated'],
                count($errors) + count($result['errors']),
                array_merge($errors, $result['errors'])
            );
        } catch (\Exception $e) {
            Log::error('Import JSON Knowledge Error', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);

            return ImportResultDTO::failed([$e->getMessage()]);
        }
    }

    private function validateItem(array $item, int $index): array
    {
        $rules = [
            'intent' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string',
            'question_ar' => 'nullable|string',
            'question_en' => 'nullable|string',
            'answer_ar' => 'nullable|string',
            'answer_en' => 'nullable|string',
            'may_need_handoff' => 'nullable|boolean',
            'requires_handoff' => 'nullable|boolean',
            'priority' => 'nullable|integer|min:0|max:255',
        ];

        $validator = Validator::make($item, $rules);

        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->all() as $error) {
                $errors[] = "Item {$index}: {$error}";
            }
            return ['valid' => false, 'errors' => $errors];
        }

        // At least one question and one answer required
        if (empty($item['question_ar']) && empty($item['question_en'])) {
            return ['valid' => false, 'errors' => ["Item {$index}: At least one question (ar or en) is required"]];
        }

        if (empty($item['answer_ar']) && empty($item['answer_en'])) {
            return ['valid' => false, 'errors' => ["Item {$index}: At least one answer (ar or en) is required"]];
        }

        return ['valid' => true, 'errors' => []];
    }

    private function normalizeItem(array $item): array
    {
        return [
            'intent' => $item['intent'],
            'category' => $item['category'] ?? null,
            'keywords' => $item['keywords'] ?? [],
            'question_ar' => $item['question_ar'] ?? null,
            'question_en' => $item['question_en'] ?? null,
            'answer_ar' => $item['answer_ar'] ?? null,
            'answer_en' => $item['answer_en'] ?? null,
            'may_need_handoff' => $item['may_need_handoff'] ?? false,
            'requires_handoff' => $item['requires_handoff'] ?? false,
            'priority' => $item['priority'] ?? 0,
            'is_active' => true,
        ];
    }
}
