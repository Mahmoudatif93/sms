<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\ContactGroup;
use App\Models\Contact;
use Illuminate\Support\Str;

class ContactGroupsRepository implements ContactGroupsRepositoryInterface
{
    protected $ContactGroup;
    protected $Contact;
    public function __construct(ContactGroup $ContactGroup, Contact  $Contact)
    {
        $this->ContactGroup = $ContactGroup;
        $this->Contact = $Contact;
    }


    public function groups($user_id)
    {

        return $this->ContactGroup->get_contact_groups($user_id);
    }


    public function find($id)
    {
        return $this->ContactGroup->find($id);
    }


    public function create(array $data)
    {
        return $this->ContactGroup->create($data);
    }

    public function update($id, array $data)
    {
        $ContactGroup = $this->ContactGroup->find($id);
        $ContactGroup->update($data);
        return $ContactGroup;
    }

    public function update_number($id, array $data)
    {
        $Contact = $this->Contact->find($id);
        $Contact->update($data);
        return $Contact;
    }


    public function delete($id)
    {

        $ContactGroup = $this->ContactGroup->find($id);
        return $ContactGroup->delete();
    }

    public function delete_number($id)
    {

        $Contact = $this->Contact->find($id);
        
        return $Contact->delete();
    }


    public function add_numbers_manual(array $data)
    {
        return $this->Contact->create($data);
    }
}
