<?php

namespace App\WhatsappMessages\Parameters;

use InvalidArgumentException;

class CurrencyParameter extends Parameter
{
    protected ?string $fallback_value;
    protected ?string $code;
    protected ?int $amount_1000;

    public function __construct(string $fallback_value = null, string $code = null, int $amount_1000 = null)
    {
        parent::__construct('currency');
        $this->fallback_value = $fallback_value;
        $this->code = $code;
        $this->amount_1000 = $amount_1000;
    }

    public function validate(): void
    {
        if (empty($this->fallbackValue) || empty($this->code) || empty($this->amount1000)) {
            throw new InvalidArgumentException("Missing fields in currency parameter");
        }
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'currency' => [
                'fallback_value' => $this->fallback_value,
                'code' => $this->code,
                'amount_1000' => $this->amount_1000
            ]
        ];
    }
}
