<?php

namespace App\Enums;

class TemplateCategory
{
    const AUTHENTICATION = 'AUTHENTICATION';
    const MARKETING = 'MARKETING';
    const UTILITY = 'UTILITY';

    public static function values(): array
    {
        return [
            self::AUTHENTICATION,
            self::MARKETING,
            self::UTILITY,
        ];
    }
}
