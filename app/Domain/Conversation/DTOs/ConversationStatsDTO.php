<?php

namespace App\Domain\Conversation\DTOs;

final readonly class ConversationStatsDTO
{
    public function __construct(
        public int $meCount = 0,
        public int $unassignedCount = 0,
        public int $archivedCount = 0,
        public int $notRepliedCount = 0,
        public int $promotionalCount = 0,
    ) {}

    public static function fromDatabaseResult(object $result): self
    {
        return new self(
            meCount: (int) ($result->me_count ?? 0),
            unassignedCount: (int) ($result->unassigned_count ?? 0),
            archivedCount: (int) ($result->archived_count ?? 0),
            notRepliedCount: (int) ($result->not_replied_count ?? 0),
            promotionalCount: (int) ($result->promotional_count ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            ['filter' => 'me', 'num' => $this->meCount],
            ['filter' => 'unassigned', 'num' => $this->unassignedCount],
            ['status' => 'archived', 'num' => $this->archivedCount],
            ['status' => 'not_replied', 'num' => $this->notRepliedCount],
            ['status' => 'promotional', 'num' => $this->promotionalCount],
        ];
    }
}
