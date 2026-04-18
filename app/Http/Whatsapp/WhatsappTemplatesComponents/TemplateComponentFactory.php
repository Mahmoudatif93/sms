<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;
use InvalidArgumentException;

class TemplateComponentFactory
{
    public static function createComponent(array $componentData): HeaderTextComponent|HeaderMediaComponent|HeaderLocationComponent|BodyComponent|null|FooterComponent|ButtonsComponent
    {
        // Check if the "type" key is present
        if (!isset($componentData['type'])) {
            throw new InvalidArgumentException('The (type) field is required for all components.');
        }
        return match ($componentData['type']) {
            'HEADER' => self::createHeaderComponent($componentData),
            'BODY' => new BodyComponent($componentData['text'], $componentData['example'] ?? []),
            'FOOTER' => new FooterComponent($componentData['text']),
            'BUTTONS' => new ButtonsComponent($componentData['buttons']),
            default => throw new InvalidArgumentException('Unsupported component type'),
        };
    }

    private static function createHeaderComponent(array $componentData): HeaderTextComponent|HeaderMediaComponent|HeaderLocationComponent
    {
        if (!isset($componentData['format'])) {
            throw new InvalidArgumentException('The "format" field is required for the HEADER component.');
        }


        return match ($componentData['format']) {
            'TEXT' => new HeaderTextComponent(
                $componentData['format'],
                $componentData['text'] ?? throw new InvalidArgumentException('The "text" field is required for TEXT format in HEADER.'),
                $componentData['example'] ?? null
            ),
            'IMAGE', 'VIDEO', 'DOCUMENT' => new HeaderMediaComponent(
                $componentData['format'],
                $componentData['example'] ?? throw new InvalidArgumentException('The "example" field with "header_handle" is required for media formats (IMAGE, VIDEO, DOCUMENT) in HEADER.')
            ),
            'LOCATION' => new HeaderLocationComponent(),
            default => throw new InvalidArgumentException('Unsupported HEADER format: ' . $componentData['format']),
        };
    }
}
