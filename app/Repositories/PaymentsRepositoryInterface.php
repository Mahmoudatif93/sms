<?php

namespace App\Repositories;

interface PaymentsRepositoryInterface
{
   
    public function findall($user_id,$perPage, $search);
    
}


