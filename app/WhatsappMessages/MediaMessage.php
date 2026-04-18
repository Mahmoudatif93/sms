<?php

namespace App\WhatsappMessages;

use InvalidArgumentException;

class MediaMessage extends Message
{
    protected array $message;

    public function __construct(string $to, string $type, array $details, $context = null)
    {
        parent::__construct($to, $type, $context);
        $this->message = $this->buildMessage($type, $details);
    }

    protected function buildMessage(string $type, array $details): array
    {
        $message = [

        ];

        switch ($type) {
            case 'image':
                if (isset($details['id'])) {
                    $message['image'] = [
                        'id' => $details['id'],
                        'caption' => $details['caption'] ?? null,
                    ];
                } elseif (isset($details['link'])) {
                    $message['image'] = [
                        'link' => $details['link'],
                        'provider' => $details['provider'] ?? null,
                        'caption' => $details['caption'] ?? null,
                    ];
                }
                break;
            case 'sticker':
                if (isset($details['id'])) {
                    $message['sticker'] = [
                        'id' => $details['id'],
                    ];
                } elseif (isset($details['link'])) {
                    $message['sticker'] = [
                        'link' => $details['link'],
                        'provider' => $details['provider'] ?? null,
                    ];
                }
                break;
            default:
                throw new InvalidArgumentException("Unsupported media type: {$type}");
        }

        return $message;
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        return array_merge($message, $this->message);
    }
}
