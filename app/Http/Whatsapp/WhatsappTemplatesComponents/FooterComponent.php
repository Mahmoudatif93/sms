<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;
use InvalidArgumentException;

class FooterComponent implements TemplateComponent
{
    private string $type = "FOOTER";
    private string $text;

    public function __construct(string $text)
    {
        // Validate text length (60 characters max)
        if (mb_strlen($text) > 60) {
            throw new InvalidArgumentException('The footer text must be 60 characters or fewer.');
        }

        $this->text = $text;

        // Add any other validations if needed
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function toArray(): array
    {
        return [
            'type' => 'FOOTER',
            'text' => $this->text,
        ];
    }
}

