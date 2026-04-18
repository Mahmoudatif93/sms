<?php

namespace App\Domain\Conversation\Requests\Widget;

use Illuminate\Foundation\Http\FormRequest;

class GetChatHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|uuid|exists:conversations,id',
            'before_id' => 'nullable|uuid',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function getSessionId(): string
    {
        return $this->input('session_id');
    }

    public function getBeforeId(): ?string
    {
        return $this->input('before_id');
    }

    public function getLimit(): int
    {
        return $this->input('limit', 50);
    }
}
