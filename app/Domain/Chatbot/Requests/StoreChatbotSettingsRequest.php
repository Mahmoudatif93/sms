<?php

namespace App\Domain\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatbotSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_enabled' => 'sometimes|boolean',
            'welcome_message_ar' => 'nullable|string|max:2000',
            'welcome_message_en' => 'nullable|string|max:2000',
            'fallback_message_ar' => 'nullable|string|max:2000',
            'fallback_message_en' => 'nullable|string|max:2000',
            'system_prompt' => 'nullable|string|max:5000',
            'handoff_threshold' => 'sometimes|integer|min:1|max:10',
            'handoff_keywords' => 'nullable|array',
            'handoff_keywords.*' => 'string|max:100',
            'ai_model' => 'sometimes|string|in:gpt-4o-mini,gpt-4o,gpt-3.5-turbo',
            'max_tokens' => 'sometimes|integer|min:50|max:2000',
            'temperature' => 'sometimes|numeric|min:0|max:1',
        ];
    }

    public function messages(): array
    {
        return [
            'handoff_threshold.min' => 'Handoff threshold must be at least 1',
            'handoff_threshold.max' => 'Handoff threshold cannot exceed 10',
            'ai_model.in' => 'Invalid AI model selected',
        ];
    }
}
