<?php

namespace App\Domain\Conversation\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

class MarkMessagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|uuid|exists:conversations,id',
            'message_ids' => 'nullable|array',
            'message_ids.*' => 'required|uuid|exists:livechat_messages,id',
        ];
    }

    public function getSessionId(): string
    {
        return $this->input('session_id');
    }

    public function getMessageIds(): ?array
    {
        return $this->input('message_ids');
    }
}
