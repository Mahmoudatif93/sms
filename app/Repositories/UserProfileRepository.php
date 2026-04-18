<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Str;

class UserProfileRepository implements UserProfileRepositoryInterface
{
    protected $UserProfile;

    public function __construct(User $UserProfile)
    {
        $this->UserProfile = $UserProfile;
    }

    public function findall($user_id)
    {

        return  User::where('user_id', $user_id)->get();
    }


    public function find($id)
    {
        return $this->UserProfile->findOrFail($id);
    }

    public function update($id, array $data)
    {
        $UserProfile = $this->find($id);
        $UserProfile->update($data);
        return $UserProfile;
    }
    public function  check_email_availability($email)
    {
        return  User::where('email', $email)->first();
    }
    public function  check_number_availability($number)
    {
        return  User::where('number', $number)->first();
    }

    public function refrsh_key($id, array $data)
    {
        $UserProfile = $this->find($id);
        $UserProfile->update($data);
        return $UserProfile;
    }
    public function notification_save($id, array $data)
    {
        $UserProfile = $this->find($id);
        $UserProfile->update($data);
        return $UserProfile;
    }
    
}
