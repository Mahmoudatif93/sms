<?php

namespace App\Repositories;

interface UserOtpRepositoryInterface
{

    public function findall($organizationId, $perPage,$search);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);
    public function delete($id);
}


