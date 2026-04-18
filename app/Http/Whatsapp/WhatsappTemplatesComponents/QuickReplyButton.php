<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class QuickReplyButton
{
    private string $type = 'QUICK_REPLY';
    private string $text;

    public function __construct(string $text)
    {
        if (mb_strlen($text) > 25) {
            throw new InvalidArgumentException('Quick reply text cannot exceed 25 characters.');
        }

        $this->text = $text;
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
            'type' => $this->type,
            'text' => $this->text,
        ];
    }
}
