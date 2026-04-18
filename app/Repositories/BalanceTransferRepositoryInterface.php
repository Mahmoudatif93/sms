<?php

namespace App\Repositories;

interface BalanceTransferRepositoryInterface
{

    public function findall($user_id, $perPage, $search);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);

    public function getIdByUsername($username);


    public function findlogs($user_id, $perPage,$search);
}
