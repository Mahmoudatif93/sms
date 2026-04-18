<?php

namespace App\Domain\Messenger\DTOs;

use Illuminate\Http\UploadedFile;

class UpdateMessengerTemplateDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $type = null,
        public readonly ?array $payload = null,
        public readonly ?bool $isActive = null,
        public readonly ?array $images = null,
        public readonly ?UploadedFile $mediaFile = null,
        public readonly ?UploadedFile $couponImage = null,
    ) {}

    public static function fromRequest(
        array $validated,
        ?array $images = null,
        ?UploadedFile $mediaFile = null,
        ?UploadedFile $couponImage = null
    ): self {
        return new self(
            name: $validated['name'] ?? null,
            type: $validated['type'] ?? null,
            payload: $validated['payload'] ?? null,
            isActive: $validated['is_active'] ?? null,
            images: $images,
            mediaFile: $mediaFile,
            couponImage: $couponImage,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'payload' => $this->payload,
            'is_active' => $this->isActive,
        ], fn($value) => $value !== null);
    }
}
