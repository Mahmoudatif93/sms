<?php

namespace App\Domain\Messenger\DTOs;

use App\Models\MessengerTemplate;

class MessengerTemplateResultDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $metaPageId,
        public readonly string $name,
        public readonly string $type,
        public readonly array $payload,
        public readonly bool $isActive,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromModel(MessengerTemplate $template): self
    {
        return new self(
            id: $template->id,
            metaPageId: $template->meta_page_id,
            name: $template->name,
            type: $template->type,
            payload: $template->getPayloadWithMediaUrls(),
            isActive: $template->is_active,
            createdAt: $template->created_at->toISOString(),
            updatedAt: $template->updated_at->toISOString(),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'meta_page_id' => $this->metaPageId,
            'name' => $this->name,
            'type' => $this->type,
            'payload' => $this->payload,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
