<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\WidgetMessageDTO;
use Illuminate\Foundation\Http\FormRequest;

class SendWidgetMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'required|uuid|exists:conversations,id',
            'content_type' => 'required|string|in:text,file',
            'message' => 'required_if:content_type,text|string',
            'file' => 'required_if:content_type,file|file|max:10240',
            'caption' => 'nullable|string',
            'replied_message_id' => 'nullable|uuid|exists:livechat_messages,id',
        ];
    }

    public function toDTO(): WidgetMessageDTO
    {
        $data = $this->validated();
        $data['file'] = $this->file('file');

        return WidgetMessageDTO::fromRequest($data);
    }
}
