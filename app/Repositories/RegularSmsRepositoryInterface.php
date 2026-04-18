<?php

namespace App\Repositories;

interface RegularSmsRepositoryInterface
{
   

    public function findall($user_id);
    
    public function find($id);

    public function create(array $data);

    public function update($id, array $data);
    public function get_favorite_messages($user_id);
    public function get_unclassified_cnt_by_user_id($user_id);
    public function get_contact_groups($user_id);
    public function get_granted_contact_groups($user_id);
    public function senders($user_id);
    public function get_granted_senders($user_id);
}


