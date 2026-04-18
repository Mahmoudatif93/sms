<?php

namespace App\Domain\Conversation\Requests\LiveChat;

use Illuminate\Foundation\Http\FormRequest;

class SendReactionMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reaction' => 'required|array',
            'reaction.message_id' => 'required|uuid|exists:livechat_messages,id',
            'reaction.emoji' => ['present', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'reaction.required' => 'Reaction content is required',
            'reaction.message_id.required' => 'Message ID is required',
            'reaction.message_id.exists' => 'Message not found',
            'reaction.emoji.present' => 'Emoji field must be present',
        ];
    }
}
