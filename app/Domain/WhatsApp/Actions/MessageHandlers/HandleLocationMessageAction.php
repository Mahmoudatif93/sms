<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;
use App\Models\WhatsappLocationMessage;

class HandleLocationMessageAction extends BaseIncomingMessageAction
{
    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_LOCATION;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        // أنشئ الـ Location record
        return $this->repository->createLocationMessage(
            $dto->messageId,
            $dto->getLatitude(),
            $dto->getLongitude(),
            $dto->getLocationName(),
            $dto->getLocationAddress()
        );
    }

    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return null; // رسائل الـ location ليس لها نص للترجمة
    }
}
