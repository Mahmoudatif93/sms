<?php

namespace App\Domain\Conversation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'platform' => 'required|string|in:whatsapp,livechat,messenger',
            'channel_id' => 'required|uuid|exists:channels,id',
            'contact_id' => 'required|uuid|exists:contacts,id',
            'message' => 'nullable|string|max:4096',
            'inbox_agent_id' => 'nullable|uuid|exists:users,id',
        ];
    }

    public function messages(): array
    {
        return [
            'platform.required' => 'Platform is required.',
            'platform.in' => 'Platform must be one of: whatsapp, livechat, messenger.',
            'channel_id.required' => 'Channel ID is required.',
            'channel_id.exists' => 'The specified channel does not exist.',
            'contact_id.required' => 'Contact ID is required.',
            'contact_id.exists' => 'The specified contact does not exist.',
        ];
    }
}
