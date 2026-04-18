<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;

class HeaderLocationComponent implements TemplateComponent
{
    private string $type = 'HEADER';
    private string $format = 'LOCATION';

    public function __construct()
    {
        // Nothing to initialize for location headers
    }

    public function toJson(): array
    {
        return [
            'type' => $this->type,
            'format' => $this->format,
        ];
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFormat(): string
    {
        return $this->format;
    }
}
