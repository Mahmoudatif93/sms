<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\User;
use App\Traits\UserTrait;

class Login extends DataInterface
{
    use UserTrait;

    public array $user;
    public OAuth2AccessToken $token;


    public function __construct(User $user, array $token)
    {
        $this->user = $this->format($user);
        $this->token = new OAuth2AccessToken($token);

    }

}
