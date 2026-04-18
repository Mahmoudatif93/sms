<?php

namespace App\Domain\WhatsApp\DTOs;

class IncomingMessageDTO
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $type,
        public readonly string $phoneNumberId,
        public readonly string $whatsappBusinessAccountId,
        public readonly string $timestamp,
        public readonly MessageSenderDTO $sender,
        public readonly MessageContextDTO $context,
        public readonly array $payload,
    ) {}

    public static function fromWebhookData(
        array $message,
        string $whatsappBusinessAccountId,
        string $phoneNumberId,
        array $contactsMap = []
    ): self {
        $fromWaId = $message['from'];

        return new self(
            messageId: $message['id'],
            type: $message['type'] ?? 'unknown',
            phoneNumberId: $phoneNumberId,
            whatsappBusinessAccountId: $whatsappBusinessAccountId,
            timestamp: $message['timestamp'],
            sender: MessageSenderDTO::fromWebhookData($fromWaId, $whatsappBusinessAccountId, $contactsMap),
            context: MessageContextDTO::fromWebhookData($message['context'] ?? null),
            payload: $message,
        );
    }

    public function getTextBody(): ?string
    {
        return $this->payload['text']['body'] ?? null;
    }

    public function getPreviewUrl(): ?string
    {
        return $this->payload['preview_url'] ?? null;
    }

    public function getMediaId(): ?string
    {
        return $this->payload[$this->type]['id'] ?? null;
    }

    public function getCaption(): ?string
    {
        return $this->payload[$this->type]['caption'] ?? null;
    }

    public function getInteractiveType(): ?string
    {
        return $this->payload['interactive']['type'] ?? null;
    }

    public function getInteractivePayload(): array
    {
        return $this->payload['interactive'] ?? [];
    }

    public function getReactionEmoji(): ?string
    {
        return $this->payload['reaction']['emoji'] ?? null;
    }

    public function getReactedMessageId(): ?string
    {
        return $this->payload['reaction']['message_id'] ?? null;
    }

    public function getButtonText(): ?string
    {
        return $this->payload['button']['text'] ?? null;
    }

    public function getDocumentFilename(): ?string
    {
        return $this->payload['document']['filename'] ?? null;
    }

    public function getDocumentUrl(): ?string
    {
        return $this->payload['document']['url'] ?? null;
    }


    public function getStickerMediaId(): ?string
    {
        return $this->payload['sticker']['id'] ?? null;
    }

    public function isAnimatedSticker(): bool
    {
        return (bool) ($this->payload['sticker']['animated'] ?? false);
    }

    public function getStickerMimeType(): ?string
    {
        return $this->payload['sticker']['mime_type'] ?? null;
    }

    public function getLatitude(): ?float
    {
        return isset($this->payload['location']['latitude'])
            ? (float) $this->payload['location']['latitude']
            : null;
    }

    public function getLongitude(): ?float
    {
        return isset($this->payload['location']['longitude'])
            ? (float) $this->payload['location']['longitude']
            : null;
    }

    public function getLocationName(): ?string
    {
        return $this->payload['location']['name'] ?? null;
    }

    public function getLocationAddress(): ?string
    {
        return $this->payload['location']['address'] ?? null;
    }
}
