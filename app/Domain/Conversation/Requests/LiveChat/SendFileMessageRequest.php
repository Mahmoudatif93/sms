<?php

namespace App\Domain\Conversation\Requests\LiveChat;

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
            'files.*.file' => 'required|file|max:10240',
            'files.*.type' => 'required|string|in:image,video,audio,document',
            'files.*.caption' => 'nullable|string|max:1000',
            'reply_to_message_id' => 'nullable|uuid|exists:livechat_messages,id',
        ];
    }

    public function messages(): array
    {
        return [
            'files.required' => 'At least one file is required',
            'files.min' => 'At least one file is required',
            'files.*.file.required' => 'File is required',
            'files.*.file.max' => 'File size must not exceed 10MB',
            'files.*.type.required' => 'File type is required',
            'files.*.type.in' => 'Invalid file type. Allowed: image, video, audio, document',
            'files.*.caption.max' => 'Caption must not exceed 1000 characters',
        ];
    }
}
