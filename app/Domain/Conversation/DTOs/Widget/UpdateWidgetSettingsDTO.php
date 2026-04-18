<?php

namespace App\Domain\Conversation\DTOs\Widget;

class UpdateWidgetSettingsDTO
{
    public function __construct(
        public readonly string $widgetId,
        public readonly ?string $language = null,
        public readonly ?string $welcomeMessage = null,
        public readonly ?string $messagePlaceholder = null,
        public readonly ?string $themeColor = null,
        public readonly ?string $name = null,
        public readonly ?array $allowedDomains = null,
        public readonly ?string $position = null,
        public readonly mixed $logo = null,
    ) {}

    public static function fromRequest(string $widgetId, array $data): self
    {
        return new self(
            widgetId: $widgetId,
            language: $data['language'] ?? null,
            welcomeMessage: $data['welcome_message'] ?? null,
            messagePlaceholder: $data['message_placeholder'] ?? null,
            themeColor: $data['theme_color'] ?? null,
            name: $data['name'] ?? null,
            allowedDomains: $data['allowed_domains'] ?? null,
            position: $data['position'] ?? null,
            logo: $data['logo'] ?? null,
        );
    }

    public function hasLogo(): bool
    {
        return $this->logo !== null;
    }

    public function isBase64Logo(): bool
    {
        return is_string($this->logo) && preg_match('/^data:image\/(\w+);base64,/', $this->logo);
    }
}
