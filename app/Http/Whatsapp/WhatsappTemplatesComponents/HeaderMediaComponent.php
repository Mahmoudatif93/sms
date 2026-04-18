<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;
use InvalidArgumentException;

class HeaderMediaComponent implements TemplateComponent
{
    private string $type = 'HEADER';
    private string $format;
    private array $example;

    public function __construct(string $format, array $example = null)
    {
        $this->validateFormat($format);
        $this->validateExample($example);

        $this->format = $format;
        $this->example = $example;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getExample(): array
    {
        return $this->example;
    }

    public function toJson(): array
    {
        return [
            'type' => $this->type,
            'format' => $this->format,
            'example' => $this->example,
        ];
    }

    private function validateFormat(string $format): void
    {
        $allowedFormats = ['IMAGE', 'VIDEO', 'DOCUMENT'];
        if (!in_array($format, $allowedFormats)) {
            throw new InvalidArgumentException("Invalid media format: $format. Must be one of IMAGE, VIDEO, or DOCUMENT.");
        }
    }

    private function validateExample(?array $example): void
    {
        if (
            $example === null ||
            !isset($example['header_handle'][0]) ||
            !is_string($example['header_handle'][0]) ||
            empty($example['header_handle'][0])
        ) {
            throw new InvalidArgumentException("Media header must include a non-empty 'header_handle' in the example.");
        }
    }
}
