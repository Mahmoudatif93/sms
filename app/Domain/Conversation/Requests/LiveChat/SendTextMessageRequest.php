<?php

namespace App\Domain\Conversation\Requests\LiveChat;

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
            'reply_to_message_id' => 'nullable|uuid|exists:livechat_messages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Message content is required',
            'message.max' => 'Message must not exceed 4096 characters',
            'reply_to_message_id.exists' => 'Reply message not found',
        ];
    }
}
