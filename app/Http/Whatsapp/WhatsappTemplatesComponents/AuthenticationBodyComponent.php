<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use App\Http\Interfaces\TemplateComponent;

class AuthenticationBodyComponent implements TemplateComponent
{

    private string $type = "body";
    private ?bool $addSecurityRecommendation;

    public function getAddSecurityRecommendation(): ?bool
    {
        return $this->addSecurityRecommendation;
    }

    public function __construct(?bool $addSecurityRecommendation = null)
    {
        $this->addSecurityRecommendation = $addSecurityRecommendation;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        $component = [
            'type' => $this->type,
        ];

        if ($this->addSecurityRecommendation !== null) {
            $component['add_security_recommendation'] = $this->addSecurityRecommendation;
        }

        return $component;
    }
}
