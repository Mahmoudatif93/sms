<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;
use InvalidArgumentException;

class ButtonsComponent implements TemplateComponent
{
    private string $type = "BUTTONS";
    private array $buttons = [];

    public function __construct(array $buttons)
    {
        if (count($buttons) > 10) {
            throw new InvalidArgumentException('The buttons component can have a maximum of 10 buttons.');
        }

        foreach ($buttons as $button) {
            $this->buttons[] = $this->createButton($button);
        }
    }

    private function createButton(array $button): PhoneNumberButton|UrlButton|QuickReplyButton|CopyCodeButton|FlowButton
    {
        if (!isset($button['type'])) {
            throw new InvalidArgumentException('Each button must have a type.');
        }

        return match ($button['type']) {
            'PHONE_NUMBER' => new PhoneNumberButton($button['text'], $button['phone_number']),
            'URL' => new UrlButton($button['text'], $button['url'], $button['example'] ?? []),
            'QUICK_REPLY' => new QuickReplyButton($button['text']),
            'COPY_CODE' => new CopyCodeButton($button['example'][0]),
            'FLOW' => new FlowButton($button['text'], $button['flow_id'] ?? null, $button['flow_json'] ?? null, $button['flow_action'] ?? 'navigate', $button['navigate_screen'] ?? null),
            'OTP' => new OtpButton(
                $button['otp_type'],
                $button['text'] ?? null,
                $button['autofill_text'] ?? null,
                $button['zero_tap_terms_accepted'] ?? null,
                $button['supported_apps'] ?? []
            ),
            default => throw new InvalidArgumentException('Unsupported button type: ' . $button['type']),
        };
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getButtons(): array
    {
        return $this->buttons;
    }

    public function toArray(): array
    {
        return [
            'type' => 'BUTTONS',
            'buttons' => array_map(function ($button) {
                return $button->toArray();
            }, $this->buttons),
        ];
    }
}

