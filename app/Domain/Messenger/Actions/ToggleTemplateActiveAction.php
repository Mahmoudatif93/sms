<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\MessengerTemplateResultDTO;
use App\Domain\Messenger\Events\MessengerTemplateUpdated;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use App\Models\MessengerTemplate;

class ToggleTemplateActiveAction
{
    public function __construct(
        private MessengerTemplateRepositoryInterface $repository
    ) {}

    public function execute(MessengerTemplate $template): MessengerTemplateResultDTO
    {
        $this->repository->update($template, ['is_active' => !$template->is_active]);

        $template = $template->fresh();

        event(new MessengerTemplateUpdated($template));

        return MessengerTemplateResultDTO::fromModel($template);
    }
}
