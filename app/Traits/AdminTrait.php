<?php

namespace App\Traits;

use App\Models\Supervisor as SupervisorModel;

trait AdminTrait
{
    /**
     * Format the user data to include all necessary fields.
     *
     * @param SupervisorModel $user
     * @return array
     */
    private function format(SupervisorModel $user): array
    {

        return [
            'id' => $user->id,
            'group_id' => $user->group_id,
            'username' => $user->username,
            'email' => $user->email,
            'number' => $user->number,
            'lang' => $user->lang,
            'status' => $user->status,
            'secret_key' => $user->secret_key,
            'otp' => $user->otp,
            'password_expiration_at' => $user->password_expiration_at,
        ];
    }
}
