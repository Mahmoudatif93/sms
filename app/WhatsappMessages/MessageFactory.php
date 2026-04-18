<?php

namespace App\WhatsappMessages;

use App\Http\Whatsapp\WhatsappTemplatesComponents\BodyComponent;
use App\Http\Whatsapp\WhatsappTemplatesComponents\HeaderComponent;
use InvalidArgumentException;

class MessageFactory
{

    public static function createComponent(array $componentData): HeaderComponent|BodyComponent
    {
        return match ($componentData['type']) {
            'HEADER' => new HeaderComponent($componentData['format'], $componentData['text'], $componentData['example'] ?? null),
            'BODY' => new BodyComponent($componentData['text'], $componentData['example'] ?? []),
//            'FOOTER' => new FooterComponent(),
//            'BUTTONS' => new ButtonsComponent(),
            default => throw new InvalidArgumentException('Unsupported component type'),
        };
    }

}
