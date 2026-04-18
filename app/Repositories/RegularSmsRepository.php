<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Ticket;
use Illuminate\Support\Str;
use App\Models\Favorit;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Sender;
class RegularSmsRepository implements RegularSmsRepositoryInterface
{
    protected $Tickets;

    public function __construct(Ticket $Tickets)
    {
        $this->Tickets = $Tickets;
    }



    public function findall($user_id)
    {

        return  Ticket::where('user_id', $user_id)->get();
    }


    public function find($id)
    {
        return $this->Tickets->findOrFail($id);
    }


    public function create(array $data)
    {
        return $this->Tickets->create($data);
    }

    public function update($id, array $data)
    {
        $ticket = $this->Tickets->find($id);
        $datastatus = $ticket->status == 'closed' ? 0 : 5;
        $data['status'] = $datastatus;
        $Tickets = $this->find($id);
        $Tickets->update($data);
        return $Tickets;
    }

    public function get_favorite_messages($user_id)
    {
        return  Favorit::get_by_user_id($user_id);
    }

    public function get_unclassified_cnt_by_user_id($user_id)
    {
        return  Contact::get_unclassified_cnt_by_user_id($user_id);
    }

    public function get_contact_groups($user_id)
    {
        return  ContactGroup::get_contact_groups($user_id);
    }
    public function get_granted_contact_groups($user_id)
    {
        return  ContactGroup::get_granted_contact_groups($user_id);
    }

    public function senders($user_id)
    {
        return  Sender::get_active_by_user_id($user_id);
    }
    public function get_granted_senders($user_id)
    {
        return  Sender::get_by_ids_user_id($user_id);
    }
}
