<?php

namespace App\Domain\WhatsApp\DTOs;

class MessageSenderDTO
{
    public function __construct(
        public readonly string $waId,
        public readonly string $phoneNumber,
        public readonly string $whatsappBusinessAccountId,
        public readonly ?string $name = null,
    ) {}

    public static function fromWebhookData(
        string $waId,
        string $whatsappBusinessAccountId,
        array $contactsMap = []
    ): self {
        return new self(
            waId: $waId,
            phoneNumber: self::normalizePhoneNumber($waId),
            whatsappBusinessAccountId: $whatsappBusinessAccountId,
            name: $contactsMap[$waId] ?? null,
        );
    }

    private static function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remove any non-numeric characters except leading +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // Ensure it starts with +
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }
}
