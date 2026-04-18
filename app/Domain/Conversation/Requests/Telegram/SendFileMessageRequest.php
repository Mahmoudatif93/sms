<?php

namespace App\Domain\Conversation\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

class SendFileMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1',

            // each file item
            'files.*.file' => 'required|file|max:51200', // 50MB (Telegram limit placeholder)
            'files.*.type' => 'required|string|in:image,video,audio,document',
            'files.*.caption' => 'nullable|string|max:1024',

            'reply_to_message_id' => 'nullable|uuid|exists:telegram_messages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'At least one file is required',
            'files.array' => 'Files must be an array',
            'files.min' => 'At least one file is required',

            'files.*.file.required' => 'File is required',
            'files.*.file.file' => 'Invalid file',
            'files.*.file.max' => 'File size must not exceed 50MB',

            'files.*.type.required' => 'File type is required',
            'files.*.type.in' => 'Invalid file type. Allowed: image, video, audio, document',

            'files.*.caption.max' => 'Caption must not exceed 1024 characters',

            'reply_to_message_id.uuid' => 'Reply message ID must be a valid UUID',
            'reply_to_message_id.exists' => 'Reply message not found',
        ];
    }
}
