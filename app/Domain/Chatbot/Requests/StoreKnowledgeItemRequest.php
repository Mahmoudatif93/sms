<?php

namespace App\Domain\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intent' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'keywords' => 'nullable|array',
            'keywords.*' => 'string|max:100',
            'question_ar' => 'nullable|string|max:5000',
            'question_en' => 'nullable|string|max:5000',
            'answer_ar' => 'nullable|string|max:10000',
            'answer_en' => 'nullable|string|max:10000',
            'may_need_handoff' => 'sometimes|boolean',
            'requires_handoff' => 'sometimes|boolean',
            'priority' => 'sometimes|integer|min:0|max:255',
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $data = $this->all();

            // At least one question required
            if (empty($data['question_ar']) && empty($data['question_en'])) {
                $validator->errors()->add('question', 'At least one question (Arabic or English) is required');
            }

            // At least one answer required
            if (empty($data['answer_ar']) && empty($data['answer_en'])) {
                $validator->errors()->add('answer', 'At least one answer (Arabic or English) is required');
            }
        });
    }
}
