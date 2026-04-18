<?php

namespace App\Domain\WhatsApp\Actions\MessageHandlers;

use App\Domain\WhatsApp\Actions\BaseIncomingMessageAction;
use App\Domain\WhatsApp\DTOs\IncomingMessageDTO;
use App\Models\WhatsappMessage;
use App\Traits\WhatsappMediaManager;

class HandleStickerMessageAction extends BaseIncomingMessageAction
{
    use WhatsappMediaManager;

    public function execute(IncomingMessageDTO $dto): ?WhatsappMessage
    {
        return $this->executeCommon($dto);
    }

    protected function getMessageType(): string
    {
        return WhatsappMessage::MESSAGE_TYPE_STICKER;
    }

    protected function createMessageContent(IncomingMessageDTO $dto, WhatsappMessage $whatsappMessage): object
    {
        $mediaId = $dto->getMediaId();

        $stickerMessage = $this->repository->createStickerMessage(
            $dto->messageId,
            $mediaId,
            $dto->isAnimatedSticker(),
            $dto->getStickerMimeType()
        );


        // Stickers are webp
        $this->downloadAndStoreMedia($stickerMessage, $mediaId, 'whatsapp-stickers');

        return $stickerMessage;
    }

    protected function getTextForTranslation(IncomingMessageDTO $dto): ?string
    {
        return null; // stickers have no text
    }

    private function downloadAndStoreMedia(object $messageContent, string $mediaId, string $collection): void
    {
        $media = $this->downloadMediaFromWhatsAppCloudAPIV2($mediaId);

        if (!$media || empty($media['url'])) {
            return;
        }

        $accessToken = \App\Constants\Meta::ACCESS_TOKEN;
        $content = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->get($media['url'])
            ->body();

        $fileName = "whatsapp_sticker_{$mediaId}.webp";

        $messageContent
            ->addMediaFromStream($content)
            ->usingFileName($fileName)
            ->toMediaCollection($collection, 'oss');
    }
}
