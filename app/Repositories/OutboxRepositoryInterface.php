<?php

namespace App\Repositories;

interface OutboxRepositoryInterface
{
    public function findAll($userId,$perPage,$search);
}