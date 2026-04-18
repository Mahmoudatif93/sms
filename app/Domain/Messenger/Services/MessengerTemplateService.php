<?php

namespace App\Domain\Messenger\Services;

use App\Domain\Messenger\DTOs\MessengerTemplateResultDTO;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use App\Models\MessengerTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Service for MessengerTemplate orchestration.
 * Business logic is handled by Actions.
 */
class MessengerTemplateService
{
    public function __construct(
        private MessengerTemplateRepositoryInterface $repository
    ) {}

    public function list(string $metaPageId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getByMetaPage($metaPageId, $filters, $perPage);
    }

    public function find(string $metaPageId, string $templateId): ?MessengerTemplate
    {
        return $this->repository->findByIdAndPage($templateId, $metaPageId);
    }

    public function preview(MessengerTemplate $template): array
    {
        return [
            'template' => MessengerTemplateResultDTO::fromModel($template)->toArray(),
            'messenger_payload' => $template->toMessengerPayload(),
        ];
    }
}
