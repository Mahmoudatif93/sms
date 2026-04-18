<?php

namespace App\WhatsappMessages;

class TemplateMessage extends Message
{

    protected string $templateName;
    protected string $languageCode;
    protected array $components;

    public function __construct(string $to, string $templateName, string $languageCode, array $components, $context = null)
    {
        parent::__construct($to, 'template', $context);
        $this->templateName = $templateName;
        $this->languageCode = $languageCode;
        $this->components = $components;
    }

    public function getTemplateName(): string
    {
        return $this->templateName;
    }

    public function getLanguageCode(): string
    {
        return $this->languageCode;
    }

    public function getComponents(): array
    {
        return $this->components;
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        $message['template'] = [
            'name' => $this->templateName,
            'language' => ['code' => $this->languageCode],
            'components' => array_map(function ($component) {
                return [
                    'type' => 'body',
                    'parameters' => $component['parameters']
                ];
            }, $this->components)
        ];
        return $message;
    }
}
