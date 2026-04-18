<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;

class HandleTextMessageAction extends BaseIncomingMessageAction
{
    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        // Validate text body exists
        if (empty($dto->getTextBody())) {
            return null;
        }
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_TEXT;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        return $this->repository->createTextMessage(
            $dto->messageId,
            $dto->getTextBody(),
            $dto->getPreviewUrl()
        );
    }

    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return $dto->getTextBody();
    }
}
