<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\Supervisor;
use App\Traits\AdminTrait;

class AdminLogin extends DataInterface
{
    use AdminTrait;

    public array $supervisor;
    public LoginAccessToken $token;


    public function __construct(Supervisor $supervisor, string $token)
    {
        $this->supervisor = $this->format($supervisor);
        $this->token = new LoginAccessToken($token);

    }

}
