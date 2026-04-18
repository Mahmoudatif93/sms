<?php

namespace App\Domain\Conversation\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class SendTextMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string|max:4096',
            'reply_to_message_id' => 'nullable|uuid|exists:telegram_messages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message content is required',
            'message.string' => 'Message must be a valid string',
            'message.max' => 'Message must not exceed 4096 characters',
            'reply_to_message_id.uuid' => 'Reply message ID must be a valid UUID',
            'reply_to_message_id.exists' => 'Reply message not found',
        ];
    }
}
