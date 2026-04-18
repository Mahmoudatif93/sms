<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Support\Str;
use App\Models\Organization;

class TicketsRepository implements TicketsRepositoryInterface
{
    protected $Tickets;
    protected $TicketReply;
    public function __construct(Ticket $Tickets, TicketReply $TicketReply)
    {
        $this->Tickets = $Tickets;
        $this->TicketReply = $TicketReply;
    }


    public function findall($perPage, $organizationId, $search)
    {
        $search = $request->search ?? null;
            return  Ticket::
            when(!empty($search), function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->Where('title', 'like', '%' . $search . '%')
                    ->orWhere('content', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%');
                });
            })->
            where('organization_id', $organizationId)->orderBy('created_at', 'DESC')
                ->paginate($perPage);

    }


    public function find($id)
    {
        return $this->Tickets->with('replies')->find($id);
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


    public function createTicketReply(array $data)
    {
        return $this->TicketReply->create($data);
    }
}
