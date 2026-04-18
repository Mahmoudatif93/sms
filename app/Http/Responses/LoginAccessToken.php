<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

class LoginAccessToken extends DataInterface
{

    public string $access_token;
    public string $token_type;
    public int|float $expires_in;

    public function __construct(string $token)
    {
        $this->access_token = $token;
        $this->token_type = "Bearer";
        $this->expires_in = auth()->guard('admin')->factory()->getTTL() * 60;
    }
}
