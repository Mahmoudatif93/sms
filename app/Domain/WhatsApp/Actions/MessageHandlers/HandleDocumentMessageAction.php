<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;
use App\Traits\WhatsappMediaManager;

class HandleDocumentMessageAction extends BaseIncomingMessageAction
{
    use WhatsappMediaManager;

    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_DOCUMENT;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        $mediaId = $dto->getMediaId();
        $filename = $dto->getDocumentFilename();
        $caption = $dto->getCaption();
        $link = $dto->getDocumentUrl();

        $documentMessage = $this->repository->createDocumentMessage(
            $dto->messageId,
            $mediaId,
            $filename,
            $caption,
            $link
        );

        // Download and store document
        $this->downloadAndStoreMedia($documentMessage, $mediaId, 'whatsapp-documents');

        return $documentMessage;
    }

    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return $dto->getCaption();
    }

    private function downloadAndStoreMedia(object $messageContent, string $mediaId, string $collection): void
    {
        $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);

        if (!$media || empty($media['url'])) {
            return;
        }

        $accessToken = \App\Constants\Meta::ACCESS_TOKEN;
        $content = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($media['url'])->body();

        $fileExtension = $this->getFileExtensionFromMimeType($media['mime_type']);
        $fileName = "whatsapp_document_{$mediaId}.{$fileExtension}";

        $messageContent->addMediaFromStream($content)
            ->usingFileName($fileName)
            ->toMediaCollection($collection, 'oss');
    }
}
