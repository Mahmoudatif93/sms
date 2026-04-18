<?php

namespace App\WhatsappMessages;

class LocationMessage extends Message
{
    protected array $location;

    public function __construct(string $to, array $location, $context = null)
    {
        parent::__construct($to, 'location', $context);
        $this->location = $location;
    }

    public function toArray(): array
    {
        $message = parent::toArray();
        $message['location'] = [
            'longitude' => $this->location['longitude'],
            'latitude' => $this->location['latitude'],
            'name' => $this->location['name'],
            'address' => $this->location['address']
        ];
        return $message;
    }
}
