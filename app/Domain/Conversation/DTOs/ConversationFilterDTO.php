<?php

namespace App\Domain\Conversation\DTOs;

use Illuminate\Http\Request;

final readonly class ConversationFilterDTO
{
    public function __construct(
        public ?string $filter = null,
        public ?string $status = null,
        public string $sort = 'newest',
        public int $perPage = 15,
        public int $page = 1,
        public ?string $search = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $search = $request->get('search');

        // Remove leading zero if search starts with 0
        if ($search && str_starts_with($search, '0')) {
            $search = ltrim($search, '0');
        }

        return new self(
            filter: $request->input('filter'),
            status: $request->input('status'),
            sort: $request->input('sort', 'newest'),
            perPage: (int) $request->get('per_page', 15),
            page: (int) $request->get('page', 1),
            search: $search,
        );
    }

    public function toArray(): array
    {
        return [
            'filter' => $this->filter,
            'status' => $this->status,
            'sort' => $this->sort,
            'per_page' => $this->perPage,
            'page' => $this->page,
            'search' => $this->search,
        ];
    }
}
