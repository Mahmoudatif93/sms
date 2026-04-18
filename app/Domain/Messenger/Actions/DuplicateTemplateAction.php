<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\MessengerTemplateResultDTO;
use App\Domain\Messenger\Events\MessengerTemplateCreated;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use App\Models\MessengerTemplate;

class DuplicateTemplateAction
{
    public function __construct(
        private MessengerTemplateRepositoryInterface $repository
    ) {}

    public function execute(MessengerTemplate $template): MessengerTemplateResultDTO
    {
        $newTemplate = $this->repository->duplicate($template, $template->name . ' (Copy)');

        event(new MessengerTemplateCreated($newTemplate));

        return MessengerTemplateResultDTO::fromModel($newTemplate);
    }
}
