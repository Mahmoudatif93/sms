<?php

namespace App\WhatsappMessages;

class ContactMessage extends Message
{
    protected array $contacts;

    public function __construct(string $to, array $contacts, $context = null)
    {
        parent::__construct($to, 'contacts', $context);
        $this->contacts = $contacts;
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        $message['contacts'] = $this->contacts;
        return $message;
    }
}
