<?php

namespace App\Domain\Messenger\Repositories;

use App\Models\MessengerTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MessengerTemplateRepository implements MessengerTemplateRepositoryInterface
{
    public function __construct(
        private MessengerTemplate $model
    ) {}

    public function findById(string $id): ?MessengerTemplate
    {
        return $this->model->find($id);
    }

    public function findByIdAndPage(string $id, string $metaPageId): ?MessengerTemplate
    {
        return $this->model
            ->where('id', $id)
            ->where('meta_page_id', $metaPageId)
            ->first();
    }

    public function getByMetaPage(string $metaPageId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('meta_page_id', $metaPageId);

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function create(array $data): MessengerTemplate
    {
        return $this->model->create($data);
    }

    public function update(MessengerTemplate $template, array $data): bool
    {
        return $template->update($data);
    }

    public function delete(MessengerTemplate $template): bool
    {
        return $template->delete();
    }

    public function duplicate(MessengerTemplate $template, string $newName): MessengerTemplate
    {
        $newTemplate = $template->replicate();
        $newTemplate->name = $newName;
        $newTemplate->save();

        return $newTemplate;
    }
}
