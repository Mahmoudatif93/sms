<?php

namespace App\WhatsappMessages\Parameters;

abstract class Parameter
{
    protected string $type;

    public function __construct(string $type)
    {
        $this->type = $type;
    }

    abstract public function toArray(): array;
}

