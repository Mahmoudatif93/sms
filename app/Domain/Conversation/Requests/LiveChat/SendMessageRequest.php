<?php

namespace App\Domain\Conversation\Requests\LiveChat;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'conversation_id' => 'required|uuid|exists:conversations,id',
            'type' => 'required|string|in:text,files,reaction',
            'message' => 'required_if:type,text|string',
            'reply_to_message_id' => 'nullable|uuid|exists:livechat_messages,id',
            'files' => 'required_if:type,files|array|min:1',
            'files.*.file' => 'required_with:files|file|max:10240',
            'files.*.type' => 'required_with:files|string|in:image,video,audio,document',
            'files.*.caption' => 'nullable|string|max:1000',
            'reaction' => 'required_if:type,reaction|array',
            'reaction.message_id' => 'required_if:type,reaction|uuid|exists:livechat_messages,id',
            'reaction.emoji' => 'present_if:type,reaction|nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'conversation_id.required' => 'Conversation ID is required',
            'conversation_id.exists' => 'Conversation not found',
            'type.required' => 'Message type is required',
            'type.in' => 'Invalid message type. Allowed: text, files, reaction',
            'message.required_if' => 'Message content is required for text messages',
            'files.required_if' => 'Files are required for file messages',
            'files.*.file.max' => 'File size must not exceed 10MB',
            'content.required_if' => 'Content is required for reaction messages',
            'content.message_id.required_if' => 'Message ID is required for reactions',
        ];
    }
}
