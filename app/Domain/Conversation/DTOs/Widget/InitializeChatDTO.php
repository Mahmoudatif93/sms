<?php

namespace App\Domain\Conversation\DTOs\Widget;

class InitializeChatDTO
{
    public function __construct(
        public readonly string $widgetId,
        public readonly string $fingerprint,
        public readonly ?string $referrer = null,
        public readonly ?string $browser = null,
        public readonly ?string $sessionId = null,
        public readonly ?string $ipAddress = null,
    ) {}

    public static function fromRequest(array $data, ?string $ipAddress = null): self
    {
        return new self(
            widgetId: $data['widget_id'],
            fingerprint: $data['fingerprint'],
            referrer: $data['referrer'] ?? null,
            browser: $data['browser'] ?? null,
            sessionId: $data['session_id'] ?? null,
            ipAddress: $ipAddress,
        );
    }

    public function getVisitorData(): array
    {
        return [
            'browser' => $this->browser,
            'ip-address' => $this->ipAddress,
            'referrer' => $this->referrer,
            'last-seen' => now(),
        ];
    }
}
