<?php

namespace App\WhatsappMessages;

class ImageMessage extends Message
{

    protected array $image;
    public function __construct(string $to, string $id, ?string $caption = null, ?string $context = null)
    {
        parent::__construct($to, 'image', $context);
        $this->image = [
            'id' => $id,
            'caption' => $caption
        ];
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        $message['image'] = $this->image;

        return $message;
    }
}
