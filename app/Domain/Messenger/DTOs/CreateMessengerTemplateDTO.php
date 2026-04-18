<?php

namespace App\Domain\Messenger\DTOs;

use Illuminate\Http\UploadedFile;

class CreateMessengerTemplateDTO
{
    public function __construct(
        public readonly string $metaPageId,
        public readonly string $name,
        public readonly string $type,
        public readonly array $payload,
        public readonly bool $isActive = true,
        public readonly ?array $images = null,
        public readonly ?UploadedFile $mediaFile = null,
        public readonly ?UploadedFile $couponImage = null,
    ) {}

    public static function fromRequest(
        string $metaPageId,
        array $validated,
        ?array $images = null,
        ?UploadedFile $mediaFile = null,
        ?UploadedFile $couponImage = null
    ): self {
        return new self(
            metaPageId: $metaPageId,
            name: $validated['name'],
            type: $validated['type'],
            payload: $validated['payload'],
            isActive: $validated['is_active'] ?? true,
            images: $images,
            mediaFile: $mediaFile,
            couponImage: $couponImage,
        );
    }
}
