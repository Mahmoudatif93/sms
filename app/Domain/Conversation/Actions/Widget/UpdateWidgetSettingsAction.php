<?php

namespace App\Domain\Conversation\Actions\Widget;

use App\Domain\Conversation\DTOs\Widget\UpdateWidgetSettingsDTO;
use App\Domain\Conversation\Repositories\WidgetRepositoryInterface;
use Illuminate\Support\Str;

class UpdateWidgetSettingsAction
{
    public function __construct(
        private WidgetRepositoryInterface $widgetRepository,
    ) {}

    public function execute(UpdateWidgetSettingsDTO $dto): array
    {
        $widget = $this->widgetRepository->findByIdOrFail($dto->widgetId);

        // Prepare update data
        $updateData = [
            'language' => $dto->language ?? $widget->language,
            'position' => $dto->position ?? $widget->position,
            'welcome_message' => $dto->welcomeMessage ?? $widget->welcome_message,
            'message_placeholder' => $dto->messagePlaceholder ?? $widget->message_placeholder,
            'theme_color' => $dto->themeColor ?? $widget->theme_color,
            'allowed_domains' => ($dto->allowedDomains && !empty($dto->allowedDomains))
                ? json_encode($dto->allowedDomains)
                : null,
        ];

        $widget = $this->widgetRepository->update($widget, $updateData);

        // Handle logo upload
        $this->handleLogoUpload($widget, $dto);

        // Update channel name if provided
        if ($dto->name) {
            $channel = $widget->liveChatConfiguration->connector->channel;
            if ($channel) {
                $channel->update(['name' => $dto->name]);
            }
        }

        $widget->refresh();

        return [
            'widget' => [
                'id' => $widget->id,
                'language' => $widget->language,
                'welcome_message' => $widget->welcome_message,
                'message_placeholder' => $widget->message_placeholder,
                'theme_color' => $widget->theme_color,
                'allowed_domains' => $widget->allowed_domains,
                'logo_url' => $widget->logo_url,
            ],
        ];
    }

    private function handleLogoUpload($widget, UpdateWidgetSettingsDTO $dto): void
    {
        if (!$dto->hasLogo()) {
            return;
        }

        if ($dto->logo instanceof \Illuminate\Http\UploadedFile) {
            $widget->clearMediaCollection('logo');
            $widget->addMediaFromRequest('logo')->toMediaCollection('logo', 'oss');
            return;
        }

        if ($dto->isBase64Logo()) {
            preg_match('/^data:image\/(\w+);base64,/', $dto->logo, $matches);
            $extension = strtolower($matches[1]);
            $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'];

            if (!in_array($extension, $allowedExtensions)) {
                throw new \Exception('Invalid image format. Allowed: jpeg, png, jpg, gif, svg, webp');
            }

            $widget->clearMediaCollection('logo');
            $widget->addMediaFromBase64($dto->logo)
                ->usingFileName(Str::uuid() . '.' . $extension)
                ->toMediaCollection('logo', 'oss');
        }
    }
}
