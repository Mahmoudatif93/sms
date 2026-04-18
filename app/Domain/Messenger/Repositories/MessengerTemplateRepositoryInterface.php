<?php

namespace App\Domain\Messenger\Repositories;

use App\Models\MessengerTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface MessengerTemplateRepositoryInterface
{
    public function findById(string $id): ?MessengerTemplate;

    public function findByIdAndPage(string $id, string $metaPageId): ?MessengerTemplate;

    public function getByMetaPage(string $metaPageId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function create(array $data): MessengerTemplate;

    public function update(MessengerTemplate $template, array $data): bool;

    public function delete(MessengerTemplate $template): bool;

    public function duplicate(MessengerTemplate $template, string $newName): MessengerTemplate;
}
