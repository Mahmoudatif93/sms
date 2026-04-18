<?php

namespace App\Repositories;

interface TicketsRepositoryInterface
{

    public function findall($perPage,$organizationId, $search);

    public function find($id);

    public function create(array $data);

    public function update($id, array $data);
    public function createTicketReply(array $data);


}


