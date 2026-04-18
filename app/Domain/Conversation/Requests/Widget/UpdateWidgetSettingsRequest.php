<?php

namespace App\Domain\Conversation\Requests\Widget;

use App\Domain\Conversation\DTOs\Widget\UpdateWidgetSettingsDTO;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWidgetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'language' => 'nullable|string|max:10',
            'welcome_message' => 'nullable|string|max:255',
            'message_placeholder' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:7',
            'name' => 'nullable|string|max:255',
            'allowed_domains' => 'nullable|array',
            'position' => 'nullable|string|in:left,right',
            'logo' => 'nullable',
        ];
    }

    public function toDTO(string $widgetId): UpdateWidgetSettingsDTO
    {
        return UpdateWidgetSettingsDTO::fromRequest($widgetId, $this->validated());
    }
}
