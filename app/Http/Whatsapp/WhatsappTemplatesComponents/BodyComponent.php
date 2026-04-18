<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Helpers\TemplateValidationHelper;
use App\Http\Interfaces\TemplateComponent;

class BodyComponent implements TemplateComponent
{
    private string $type = "BODY";
    private string $text;
    private ?array $example;

    public function __construct(string $text, array $example = null)
    {

        $this->text = $text;
        $this->example = $example;

        TemplateValidationHelper::validateTextVariables($text);
        TemplateValidationHelper::validateTextExample($text, $example, 'body');
        TemplateValidationHelper::validateTextLength($text, 1024);


    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getExample(): ?array
    {
        return $this->example;
    }

    public function toArray(): array
    {
        $component = [
            'type' => 'BODY',
            'text' => $this->text
        ];

        if ($this->example !== null) {
            $component['example'] = ['body_text' => $this->example];
        }

        return $component;
    }


}
