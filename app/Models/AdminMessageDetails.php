<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use DB;

class AdminMessageDetails extends BaseMessageDetails
{
    use HasFactory;
    protected $table = 'admin_message_details';
    protected $fillable = ['message_id', 'text', 'length', 'number', 'country_id', 'operator_id', 'cost', 'response', 'status', 'gateway_id', 'key', 'encrypted', 'blocked', 'prvent_repeate', 'smpp_response', 'smpp_company', 'smpp_response_v2', 'send_dlr', 'count_try_send_dlr'];

    public static function getNumbers($message_id, $limit)
    {

        $numbers = self::where(['message_id' => $message_id, 'status' => 0])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('number')
            ->toArray();
            \DB::table('admin_message_details as md1')
            ->join(\DB::raw("(SELECT id FROM admin_message_details WHERE message_id = {$message_id} AND status = 0 ORDER BY id LIMIT {$limit}) as md2"), 'md1.id', '=', 'md2.id')
            ->update(['md1.status' => 1]);
       return $numbers;
    }


    public function scopeFilter($query, $filters, $workspaceId = null)
    {
        return $query
            ->select('admin_message.id',
                'admin_message.sender_name',
                DB::raw("CASE
                            WHEN admin_message.variables_message = 0 then admin_message.text  when admin_message.encrypted = 1 THEN
                                CAST(CAST(admin_message.text AS CHAR) AS BINARY)
                            ELSE admin_message_details.text
                        END AS text"),
                'admin_message_details.cost',
                'admin_message_details.number',
                'admin_message.status',
                'admin_message.creation_datetime',
                'admin_message.updation_datetime', 'admin_message.encrypted', 'admin_message.variables_message'
            )
            ->leftJoin('admin_message', 'admin_message_details.message_id', '=', 'admin_message.id')

            ->when($workspaceId ?? null, function ($q) use ($workspaceId) {
                $q->where('admin_message.workspace_id', '=', $workspaceId);
            })

            ->when($filters['from_date'] ?? null, function ($q) use ($filters) {
                $q->where('admin_message.creation_datetime', '>=', $filters['from_date']);
            })
            ->when($filters['till_date'] ?? null, function ($q) use ($filters) {
                $q->where('admin_message.creation_datetime', '<=', $filters['till_date']);
            })
            ->when($filters['sender_name'] ?? null, function ($q) use ($filters) {
                $q->where('admin_message.sender_name', 'LIKE', "%{$filters['sender_name']}%");
            })
            ->when($filters['search'] ?? null, function ($q) use ($filters) {
                $q->where(function ($subQuery) use ($filters) {
                    $subQuery->where('admin_message_details.text', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.number', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.cost', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.response', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.status', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.smpp_company', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('admin_message_details.smpp_response', 'LIKE', "%{$filters['search']}%");
                });
            })
            ->when($filters['number'] ?? null, function ($q) use ($filters) {
                $q->where('admin_message_details.number', '=', $filters['number']);
            })->OrderBy('admin_message.id');
    }

}
