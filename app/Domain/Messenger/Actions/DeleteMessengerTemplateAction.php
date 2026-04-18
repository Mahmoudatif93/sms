<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\Events\MessengerTemplateDeleted;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use App\Models\MessengerTemplate;

class DeleteMessengerTemplateAction
{
    public function __construct(
        private MessengerTemplateRepositoryInterface $repository
    ) {}

    public function execute(MessengerTemplate $template): bool
    {
        $templateData = $template->toArray();

        $result = $this->repository->delete($template);

        if ($result) {
            event(new MessengerTemplateDeleted($templateData));
        }

        return $result;
    }
}
