<?php

namespace App\Repositories;

interface UserProfileRepositoryInterface
{

    public function findall($user_id);

    public function find($id);
    public function update($id, array $data);
    public function check_email_availability($email);
    public function check_number_availability($number);
    public function refrsh_key($id, array $data);
    public function notification_save($id, array $data);

}


