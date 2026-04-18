<?php

namespace App\Domain\Conversation\Requests\Telegram;

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

            /* ===============================
             | Text Message
             =============================== */
            'message' => 'required_if:type,text|string',

            'reply_to_message_id' => 'nullable|uuid|exists:telegram_messages,id',

            /* ===============================
             | File Message
             =============================== */
            'files' => 'required_if:type,files|array|min:1',
            'files.*.file' => 'required_with:files|file|max:10240',
            'files.*.type' => 'required_with:files|string|in:image,video,audio,document',
            'files.*.caption' => 'nullable|string|max:1000',

            /* ===============================
             | Reaction Message
             =============================== */
            'reaction' => 'required_if:type,reaction|array',
            'reaction.message_id' => 'required_if:type,reaction|uuid|exists:telegram_messages,id',
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

            'reaction.required_if' => 'Reaction content is required',
            'reaction.message_id.required_if' => 'Message ID is required for reactions',
            'reaction.message_id.exists' => 'Message not found',
            'reaction.emoji.present_if' => 'Emoji field must be present (can be null)',
        ];
    }
}
