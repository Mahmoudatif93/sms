<?php

namespace App\WhatsappMessages;

class Message
{

    protected string $messaging_product = 'whatsapp';
    protected string $recipient_type = 'individual';
    protected string $to;
    protected string $type;
    protected string|null $context;

    public function __construct(string $to, string $type, string $context = null)
    {
        $this->to = $to;
        $this->type = $type;
        $this->context = $context;
    }

    public function toArray(): array
    {
        $message = [
            'messaging_product' => $this->messaging_product,
            'recipient_type' => $this->recipient_type,
            'to' => $this->to,
            'type' => $this->type,
        ];

        if ($this->context) {
            $message['context'] = $this->context;
        }

        return $message;
    }
}
