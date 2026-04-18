<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;

class HandleButtonMessageAction extends BaseIncomingMessageAction
{
    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        // Button messages are stored as text messages
        return WhatsappMessage::MESSAGE_TYPE_TEXT;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        $body = $dto->getButtonText();

        return $this->repository->createTextMessage(
            $dto->messageId,
            $body ?? '',
            null
        );
    }

    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return $dto->getButtonText();
    }
}
