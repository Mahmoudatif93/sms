<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\InitializeChatDTO;
use Illuminate\Foundation\Http\FormRequest;

class InitializeChatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'widget_id' => 'required|uuid|exists:widgets,id',
            'fingerprint' => 'required|string',
            'referrer' => 'nullable|string',
            'browser' => 'nullable|string',
            'session_id' => 'nullable|uuid',
        ];
    }

    public function toDTO(): InitializeChatDTO
    {
        $ipAddress = $this->header('CF-Connecting-IP') ?? $this->ip();

        return InitializeChatDTO::fromRequest($this->validated(), $ipAddress);
    }
}
