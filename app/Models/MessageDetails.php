<?php
namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageDetails extends BaseMessageDetails
{
    use HasFactory;
    protected $table    = 'message_details';
    protected $fillable = ['message_id', 'text', 'length', 'number', 'country_id', 'operator_id', 'cost', 'response', 'status', 'gateway_id', 'key', 'encrypted', 'blocked', 'prvent_repeate', 'smpp_response', 'smpp_company', 'smpp_response_v2', 'send_dlr', 'count_try_send_dlr'];

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id');
    }
    
    public static function getNumbers($message_id, $limit)
    {

        $numbers = self::where(['message_id' => $message_id, 'status' => 0])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('number')
            ->toArray();

        DB::table('message_details as md1')
            ->join(DB::raw("(SELECT id FROM message_details WHERE message_id = {$message_id} AND status = 0 ORDER BY id LIMIT {$limit}) as md2"), 'md1.id', '=', 'md2.id')
            ->update(['md1.status' => 1]);
        return $numbers;
    }

    public static function getNumberText($message_id, $limit)
    {

        $numbers = self::where(['message_id' => $message_id, 'status' => 0])
            ->orderBy('id')
            ->limit($limit)
            ->select('number', 'text')
            ->get()
            ->toArray();

        \DB::table('message_details as md1')
            ->join(\DB::raw("(SELECT id FROM message_details WHERE message_id = {$message_id} AND status = 0 ORDER BY id LIMIT {$limit}) as md2"), 'md1.id', '=', 'md2.id')
            ->update(['md1.status' => 1]);
        return $numbers;
    }


    public function getStatusAttribute($value)
    {

        return $value == 0 ? __('message.pending')
        : ($value == 1 ? __('message.under_processing')
            : ($value == 2 ? __('message.sent')
                : __('message.unknown')));
    }

    public function scopeFilter($query, $filters, $workspaceId = null,$revision=null)
    {
        return $query
            ->select('message.id',
                'message.sender_name',
                DB::raw("CASE
                            WHEN message.variables_message = 0 then message.text  when message.encrypted = 1 THEN
                                CAST(CAST(message.text AS CHAR) AS BINARY)
                            ELSE message_details.text
                        END AS text"),
                'message_details.cost',
                'message_details.number',
                'message.status',
                'message.creation_datetime',
                'message.updation_datetime', 'message.encrypted', 'message.variables_message'
            )
            ->leftJoin('message', 'message_details.message_id', '=', 'message.id')

            ->when($workspaceId ?? null, function ($q) use ($workspaceId) {
                $q->where('message.workspace_id', '=', $workspaceId);
            })

            ->when($revision !== null, function ($q) use ($revision) {
                $q->where('message.advertising', 1)
                ->where('message.deleted_by_user', 0)
                ->where('message.status', 0);
            })

            ->when($filters['from_date'] ?? null, function ($q) use ($filters) {
                $q->where('message.creation_datetime', '>=', $filters['from_date']);
            })
            ->when($filters['till_date'] ?? null, function ($q) use ($filters) {
                $q->where('message.creation_datetime', '<=', $filters['till_date']);
            })
            ->when($filters['sender_name'] ?? null, function ($q) use ($filters) {
                $q->where('message.sender_name', 'LIKE', "%{$filters['sender_name']}%");
            })
            ->when($filters['search'] ?? null, function ($q) use ($filters) {
                $q->where(function ($subQuery) use ($filters) {
                    $subQuery->where('message_details.text', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.number', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.cost', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.response', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.status', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.smpp_company', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('message_details.smpp_response', 'LIKE', "%{$filters['search']}%");
                });
            })
            ->when($filters['number'] ?? null, function ($q) use ($filters) {
                $q->where('message_details.number', '=', $filters['number']);
            })->OrderBy('message.id');
    }


    public function getTextAttribute($value)
    {
        if ($this->message && $this->message->encrypted == 1 && $this->message->variables_message == 0) {
            return $this->message ? $this->message->text : $value;
        }
        return $value;

    }

}
