<?php

namespace App\Domain\Chatbot\DTOs;

class ChatbotResponseDTO
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $message = null,
        public readonly ?string $knowledgeBaseId = null,
        public readonly ?float $confidenceScore = null,
        public readonly bool $usedAi = false,
        public readonly ?int $tokensUsed = null,
        public readonly ?float $costUsd = null,
        public readonly string $language = 'ar',
        public readonly bool $shouldHandoff = false,
        public readonly ?string $handoffReason = null,
        public readonly bool $disabled = false,
        public readonly bool $customerRequestedHandoff = false,
        public readonly string $responseType = 'normal', // normal, skip, fallback
    ) {}

    public static function fromKnowledge(
        string $message,
        string $knowledgeBaseId,
        float $confidenceScore,
        string $language = 'ar',
        bool $shouldHandoff = false
    ): self {
        return new self(
            success: true,
            message: $message,
            knowledgeBaseId: $knowledgeBaseId,
            confidenceScore: $confidenceScore,
            usedAi: false,
            language: $language,
            shouldHandoff: $shouldHandoff,
        );
    }

    public static function fromAi(
        string $message,
        int $tokensUsed,
        float $costUsd,
        string $language = 'ar',
        bool $customerRequestedHandoff = false,
        string $responseType = 'normal'
    ): self {
        return new self(
            success: true,
            message: $message,
            usedAi: true,
            tokensUsed: $tokensUsed,
            costUsd: $costUsd,
            language: $language,
            customerRequestedHandoff: $customerRequestedHandoff,
            responseType: $responseType,
        );
    }

    public static function handoff(string $reason): self
    {
        return new self(
            success: true,
            shouldHandoff: true,
            handoffReason: $reason,
        );
    }

    public static function disabled(): self
    {
        return new self(
            success: false,
            disabled: true,
        );
    }

    public static function failed(string $message): self
    {
        return new self(
            success: false,
            message: $message,
        );
    }
}
