<?php

namespace App\Domain\Chatbot\DTOs;

use App\Models\ChatbotKnowledgeBase;
use Illuminate\Support\Collection;

class KnowledgeSearchResultDTO
{
    public function __construct(
        public readonly ?ChatbotKnowledgeBase $bestMatch = null,
        public readonly ?Collection $topResults = null,
        public readonly float $confidence = 0.0,
    ) {}

    public static function empty(): self
    {
        return new self(
            bestMatch: null,
            topResults: collect(),
            confidence: 0.0,
        );
    }

    public static function fromResults(Collection $results, float $confidence): self
    {
        return new self(
            bestMatch: $results->first(),
            topResults: $results,
            confidence: $confidence,
        );
    }

    public function hasMatch(): bool
    {
        return $this->bestMatch !== null && $this->confidence > 0;
    }

    public function isConfident(float $threshold = 0.8): bool
    {
        return $this->hasMatch() && $this->confidence >= $threshold;
    }

    public function getBestAnswer(string $language = 'ar'): ?string
    {
        return $this->bestMatch?->getAnswer($language);
    }

    public function requiresHandoff(): bool
    {
        return $this->bestMatch?->requires_handoff ?? false;
    }

    public function mayNeedHandoff(): bool
    {
        return $this->bestMatch?->may_need_handoff ?? false;
    }
}
