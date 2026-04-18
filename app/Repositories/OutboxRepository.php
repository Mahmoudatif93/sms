<?php

namespace App\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use \DB;

class OutboxRepository implements OutboxRepositoryInterface
{


    public function findAll($userId, $perPage, $search)
    {
        //$page = request()->get('page', 1);
        if (!empty($search)) {
            $outboxQuery = DB::table('outbox as t1')
            ->leftJoin('user as t2', 't1.user_id', '=', 't2.id')
            ->select('t1.text', 't1.creation_datetime', 't1.sender_name')
            ->where('t1.user_id', $userId)
            ->where(function ($query) use ($search) {
                $query->Where('t1.text', 'like', '%' . $search . '%')
                    ->orWhere('t1.sender_name', 'like', '%' . $search . '%');
            });

            $messageQuery = DB::table('message as t1')
            ->leftJoin('user as t2', 't1.user_id', '=', 't2.id')
            ->select('t1.text', 't1.creation_datetime', 't1.sender_name')
            ->where('t1.user_id', $userId)
            ->where('advertising', 1)
            ->where('status', 0)
            ->where(function ($query) use ($search) {
                $query->Where('t1.text', 'like', '%' . $search . '%')
                    ->orWhere('t1.sender_name', 'like', '%' . $search . '%');
            });

        }else{
            $outboxQuery = DB::table('outbox as t1')
            ->leftJoin('user as t2', 't1.user_id', '=', 't2.id')
            ->select('t1.text', 't1.creation_datetime', 't1.sender_name')
            ->where('t1.user_id', $userId);

            $messageQuery = DB::table('message as t1')
            ->leftJoin('user as t2', 't1.user_id', '=', 't2.id')
            ->select('t1.text', 't1.creation_datetime', 't1.sender_name')
            ->where('t1.user_id', $userId)
            ->where('advertising', 1)
            ->where('status', 0);
        }

        $unionQuery = $outboxQuery->unionAll($messageQuery);

        $total = DB::table(DB::raw("({$unionQuery->toSql()}) as sub"))
            ->mergeBindings($unionQuery)
            ->count();
        if ($perPage == null) {
                $results = DB::table(DB::raw("({$unionQuery->toSql()}) as sub"))
                    ->mergeBindings($unionQuery)
                    ->orderBy('creation_datetime', 'desc')
                    // ->offset(($page - 1) * $perPage)
                    //->limit($perPage)
                    ->get();

        } else {

                $results = DB::table(DB::raw("({$unionQuery->toSql()}) as sub"))
                    ->mergeBindings($unionQuery)
                    ->orderBy('creation_datetime', 'desc')
                    // ->offset(($page - 1) * $perPage)
                    //->limit($perPage)
                    ->paginate($perPage);

        }

        return $results;
        /*$paginator = new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);*/

        //return $paginator;
    }
}
