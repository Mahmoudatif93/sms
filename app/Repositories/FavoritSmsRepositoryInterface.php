<?php

namespace App\Repositories;

interface FavoritSmsRepositoryInterface
{

    public function findAll($workspaceId,$perPage,$search);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);
    public function delete($id);

}


