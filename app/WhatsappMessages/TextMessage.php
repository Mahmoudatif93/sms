<?php

namespace App\WhatsappMessages;

class TextMessage extends Message
{
    protected array $text;

    public function __construct(string $to, array $text, $context = null)
    {
        parent::__construct($to, 'text', $context);
        $this->text = $text;
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        $message['text'] = ['body' => $this->text['body'], 'preview_url' => $this->text['preview_url']];
        return $message;
    }
}
