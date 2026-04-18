<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;
use App\Traits\WhatsappMediaManager;

class HandleVideoMessageAction extends BaseIncomingMessageAction
{
    use WhatsappMediaManager;

    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_VIDEO;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        $mediaId = $dto->getMediaId();
        $caption = $dto->getCaption();

        $videoMessage = $this->repository->createVideoMessage($dto->messageId, $mediaId, $caption);

        // Download and store video
        $this->downloadAndStoreMedia($videoMessage, $mediaId, 'whatsapp-videos');

        return $videoMessage;
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
        $fileName = "whatsapp_video_{$mediaId}.{$fileExtension}";

        $messageContent->addMediaFromStream($content)
            ->usingFileName($fileName)
            ->toMediaCollection($collection, 'oss');
    }
}
