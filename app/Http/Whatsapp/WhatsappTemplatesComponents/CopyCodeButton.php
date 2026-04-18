<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class CopyCodeButton
{
    private string $type = 'COPY_CODE';
    private string $example;

    public function __construct(string $example)
    {
        if (mb_strlen($example) > 15) {
            throw new InvalidArgumentException('The example text for COPY_CODE cannot exceed 15 characters.');
        }

        $this->example = $example;
    }

    public function getExample(): string
    {
        return $this->example;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'example' => $this->example,
        ];
    }
}
