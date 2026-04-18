<?php

namespace App\Repositories;

interface ContactGroupsRepositoryInterface
{


    public function find($id);

    public function create(array $data);
    public function update($id, array $data);
    public function update_number($id, array $data);
    public function delete($id);
    public function delete_number($id);
    public function groups($user_id);
    public function add_numbers_manual(array $data);
}
