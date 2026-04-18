<?php

namespace App\WhatsappMessages\Parameters;

use InvalidArgumentException;

class TextParameter extends Parameter
{
    protected ?string $text;
    protected ?string $key;

    public function __construct(?string $text = null)
    {
        parent::__construct('text');
        $this->text = $text;
    }

    public function validate(): void
    {
        if (mb_strlen($this->text) > 1024) {
            throw new InvalidArgumentException("Text exceeds 1024 character limit");
        }
        if ($this->text === null) {
            throw new InvalidArgumentException("Text is empty or does not exist.");
        }
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text
        ];
    }
}
