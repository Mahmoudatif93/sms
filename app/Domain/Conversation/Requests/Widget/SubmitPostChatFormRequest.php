<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\PostChatFormDTO;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPostChatFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|uuid|exists:conversations,id',
            'form_data' => 'required|array',
        ];
    }

    public function toDTO(): PostChatFormDTO
    {
        return PostChatFormDTO::fromRequest($this->validated());
    }
}
