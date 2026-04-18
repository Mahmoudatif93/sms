<?php

namespace App\Repositories;

use Illuminate\Support\Facades\Storage;
use App\Models\BalanceTransfer;
use Illuminate\Support\Str;
use App\Models\BalanceLog;

class BalanceTransferRepository implements BalanceTransferRepositoryInterface
{
    protected $BalanceTransfer;

    public function __construct(BalanceTransfer $BalanceTransfer)
    {
        $this->BalanceTransfer = $BalanceTransfer;
    }

    public function findall($user_id, $perPage, $search)
    {

        if ($perPage == null) {
            if ($search != null) {

                return BalanceTransfer::where(function ($query) use ($user_id) {
                    $query->where('sender_id', $user_id)
                        ->orWhere('receiver_id', $user_id);
                })
                    ->where(function ($query) use ($search) {
                        $query->whereHas('sender', function ($query) use ($search) {
                            $query->where('username', 'like', '%' . $search . '%');
                        })
                            ->orWhereHas('receiver', function ($query) use ($search) {
                                $query->where('username', 'like', '%' . $search . '%');
                            })
                          ->orWhere('points_cnt', 'like', '%' . $search . '%');

                    })
                    ->with('sender')
                    ->with('receiver')
                    ->orderBy('id', 'DESC')
                    ->get();
            } else {
                return  BalanceTransfer::where('sender_id', $user_id)->orWhere('receiver_id', $user_id)->with('sender')->with('receiver')->orderBy('id', 'DESC')
                    ->get();
            }
        } else {
            if ($search != null) {

                return BalanceTransfer::where(function ($query) use ($user_id) {
                    $query->where('sender_id', $user_id)
                        ->orWhere('receiver_id', $user_id);
                })
                    ->where(function ($query) use ($search) {
                        $query->whereHas('sender', function ($query) use ($search) {
                            $query->where('username', 'like', '%' . $search . '%');
                        })
                            ->orWhereHas('receiver', function ($query) use ($search) {
                                $query->where('username', 'like', '%' . $search . '%');
                            })
                            ->orWhere('points_cnt', 'like', '%' . $search . '%');
                    })
                    ->with('sender')
                    ->with('receiver')
                    ->orderBy('id', 'DESC')
                    ->paginate($perPage);
            } else {
                return  BalanceTransfer::where('sender_id', $user_id)->orWhere('receiver_id', $user_id)
                    ->with('sender')->with('receiver')->orderBy('id', 'DESC')
                    ->paginate($perPage);
            }
        }
    }


    public function find($id)
    {
        return $this->BalanceTransfer->findOrFail($id);
    }


    public function create(array $data)
    {
        return $this->BalanceTransfer->create($data);
    }

    public function update($id, array $data)
    {
        $ticket = $this->BalanceTransfer->find($id);
        $datastatus = $ticket->status == 'closed' ? 0 : 5;
        $data['status'] = $datastatus;
        $balances = $this->find($id);
        $balances->update($data);
        return $balances;
    }

    public function getIdByUsername($username)
    {

        return $this->BalanceTransfer->getIdByUsername($username);
    }


    public function findlogs($user_id, $perPage, $search)
    {


        if ($search != null) {
            return  BalanceLog::where('user_id', $user_id)->with('User')
                ->where(function ($query) use ($search) {
                    $query->where('amount', 'like', '%' . $search . '%')
                        ->orWhere('reason', 'like', '%' . $search . '%')
                        ->orWhere('created_by', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        } else {
            return  BalanceLog::where('user_id', $user_id)->with('User')->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        }
    }
}
