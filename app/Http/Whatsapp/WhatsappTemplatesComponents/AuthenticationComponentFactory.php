<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class AuthenticationComponentFactory
{
    public static function createComponent(array $componentData)
    {
        if (!isset($componentData['type'])) {
            throw new InvalidArgumentException('The "type" field is required for all components.');
        }


        return match ($componentData['type']) {
            'body' => new AuthenticationBodyComponent($componentData['add_security_recommendation'] ?? null),
            'footer' => new AuthenticationFooterComponent($componentData['code_expiration_minutes'] ?? null),
            'buttons' => new OtpButton(
                $componentData['buttons'][0]['otp_type'],
                $componentData['buttons'][0]['text'] ?? null,
                $componentData['buttons'][0]['autofill_text'] ?? null,
                $componentData['buttons'][0]['zero_tap_terms_accepted'] ?? null,
                $componentData['buttons'][0]['supported_apps'] ?? []
            ),
            default => throw new InvalidArgumentException('Unsupported component type for authentication: ' . $componentData['type']),
        };
    }

}
