<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\WidgetReactionDTO;
use Illuminate\Foundation\Http\FormRequest;

class SendReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|uuid|exists:conversations,id',
            'message_id' => 'required|uuid|exists:livechat_messages,id',
            'emoji' => 'nullable|string|max:10',
        ];
    }

    public function toDTO(): WidgetReactionDTO
    {
        return WidgetReactionDTO::fromRequest($this->validated());
    }
}
