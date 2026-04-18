<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

class OAuth2AccessToken extends DataInterface
{

    public string $access_token;
    public string $token_type;
    public int|float $expires_in;
    public string $refresh_token;


    public function __construct(array $token)
    {

        $this->access_token = $token['access_token'];
        $this->token_type = "Bearer";
        $this->expires_in = $token['expires_in'];
        $this->refresh_token = $token['refresh_token'];
    }
}
