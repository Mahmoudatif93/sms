<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\DTOs\CreateMessengerTemplateDTO;
use App\Domain\Messenger\DTOs\MessengerTemplateResultDTO;
use App\Domain\Messenger\Events\MessengerTemplateCreated;
use App\Domain\Messenger\Repositories\MessengerTemplateRepositoryInterface;
use Illuminate\Http\UploadedFile;

class CreateMessengerTemplateAction
{
    public function __construct(
        private MessengerTemplateRepositoryInterface $repository
    ) {}

    public function execute(CreateMessengerTemplateDTO $dto): MessengerTemplateResultDTO
    {
        $template = $this->repository->create([
            'meta_page_id' => $dto->metaPageId,
            'name' => $dto->name,
            'type' => $dto->type,
            'payload' => $dto->payload,
            'is_active' => $dto->isActive,
        ]);

        $this->processUploadedMedia($template, $dto->images, $dto->mediaFile, $dto->couponImage);

        $template = $template->fresh();

        event(new MessengerTemplateCreated($template));

        return MessengerTemplateResultDTO::fromModel($template);
    }

    private function processUploadedMedia($template, ?array $images, ?UploadedFile $mediaFile, ?UploadedFile $couponImage): void
    {
        // Process element images (for generic template)
        if ($images) {
            foreach ($images as $index => $file) {
                if ($file instanceof UploadedFile) {
                    $collectionName = "element_image_{$index}";
                    $template->clearMediaCollection($collectionName);
                    $template->addMedia($file)->toMediaCollection($collectionName, 'oss');

                    $payload = $template->getRawPayload();
                    if (isset($payload['elements'][$index])) {
                        $payload['elements'][$index]['image_url'] = "media:{$index}";
                        $this->repository->update($template, ['payload' => $payload]);
                    }
                }
            }
        }

        // Process media file (for media template)
        if ($mediaFile) {
            $collectionName = 'media_element_0';
            $template->clearMediaCollection($collectionName);
            $template->addMedia($mediaFile)->toMediaCollection($collectionName, 'oss');

            $payload = $template->getRawPayload();
            if (isset($payload['elements'][0])) {
                $payload['elements'][0]['url'] = 'media:0';
                $this->repository->update($template, ['payload' => $payload]);
            }
        }

        // Process coupon image (for coupon template)
        if ($couponImage) {
            $collectionName = 'coupon_image';
            $template->clearMediaCollection($collectionName);
            $template->addMedia($couponImage)->toMediaCollection($collectionName, 'oss');

            $payload = $template->getRawPayload();
            $payload['image_url'] = 'media:coupon';
            $this->repository->update($template, ['payload' => $payload]);
        }
    }
}
