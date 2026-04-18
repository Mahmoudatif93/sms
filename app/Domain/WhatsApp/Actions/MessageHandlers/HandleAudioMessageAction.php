<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;
use App\Traits\WhatsappMediaManager;

class HandleAudioMessageAction extends BaseIncomingMessageAction
{
    use WhatsappMediaManager;

    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_AUDIO;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        $mediaId = $dto->getMediaId();

        $audioMessage = $this->repository->createAudioMessage($dto->messageId, $mediaId);

        // Download and store audio
        $this->downloadAndStoreMedia($audioMessage, $mediaId, 'whatsapp-audios');

        return $audioMessage;
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
        $fileName = "whatsapp_audio_{$mediaId}.{$fileExtension}";

        $messageContent->addMediaFromStream($content)
            ->usingFileName($fileName)
            ->toMediaCollection($collection, 'oss');
    }
}
