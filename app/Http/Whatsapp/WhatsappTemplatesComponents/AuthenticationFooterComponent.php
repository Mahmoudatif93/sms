<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;

class AuthenticationFooterComponent implements TemplateComponent
{

    private string $type = "footer";
    private ?int $codeExpirationMinutes;

    public function getCodeExpirationMinutes(): ?int
    {
        return $this->codeExpirationMinutes;
    }

    public function __construct(?int $codeExpirationMinutes = null)
    {
        $this->codeExpirationMinutes = $codeExpirationMinutes;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'code_expiration_minutes' => $this->codeExpirationMinutes,
        ];
    }

}
