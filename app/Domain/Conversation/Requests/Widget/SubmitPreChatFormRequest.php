<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\PreChatFormDTO;
use Illuminate\Foundation\Http\FormRequest;

class SubmitPreChatFormRequest extends FormRequest
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

    public function toDTO(): PreChatFormDTO
    {
        return PreChatFormDTO::fromRequest($this->validated());
    }
}
