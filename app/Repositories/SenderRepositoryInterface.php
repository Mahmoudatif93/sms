<?php

namespace App\Repositories;

interface SenderRepositoryInterface
{
   

    public function find($user_id);
    public function findbyid($id);
    public function create(array $data);

    public function update($id, array $data);

    public function delete($id);
}


