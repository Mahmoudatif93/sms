<?php

namespace App\Http\Responses;
use App\Http\Whatsapp\WhatsappFlows\FlowValidationError;

class Flow
{
    public string $id;
    public string $name;
    public string $status;
    public array $categories;
    public array $validation_errors;

    public function __construct(array $flow)
    {
        $this->id = $flow['id'];
        $this->name = $flow['name'];
        $this->status = $flow['status'];
        $this->categories = $flow['categories'];
        $this->validation_errors = array_map(
            fn($error) => new FlowValidationError($error),
            $flow['validation_errors']
        );
    }
}
