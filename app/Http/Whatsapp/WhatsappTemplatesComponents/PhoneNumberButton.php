<?php

namespace App\Http\Whatsapp\WhatsappTemplatesComponents;

use InvalidArgumentException;

class PhoneNumberButton
{
    private string $type = 'PHONE_NUMBER';
    private string $text;
    private string $phoneNumber;

    public function __construct(string $text, string $phoneNumber)
    {
        if (mb_strlen($text) > 25) {
            throw new InvalidArgumentException('Button text cannot exceed 25 characters.');
        }
        if (mb_strlen($phoneNumber) > 20) {
            throw new InvalidArgumentException('Phone number cannot exceed 20 characters.');
        }

        $this->text = $text;
        $this->phoneNumber = $phoneNumber;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'text' => $this->text,
            'phone_number' => $this->phoneNumber,
        ];
    }
}

