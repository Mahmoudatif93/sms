<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditLog extends Model
{
    use HasFactory;
    protected $table = 'audit_log'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'event_description',
        'entity_type', 'entity_id', 'changes',
        'created_by_id', 'created_by_type', 'ip_address', 'user_agent',
    ];

    public function getAllWithUnion(array $condition = [])
    {
        $unionQuery = DB::table('supervisor')
            ->select('id', 'username', DB::raw("'Supervisor' as userType"))
            ->union(
                DB::table('user')
                    ->select('id', 'username', DB::raw("'User' as userType"))
            );

        $query = DB::table('audit_log')
            ->select('audit_log.*', 'unioned.username')
            ->joinSub($unionQuery, 'unioned', function ($join) {
                $join->on('audit_log.created_by_id', '=', 'unioned.id')
                     ->whereRaw('audit_log.created_by_type = unioned.userType');
            });

        if (!empty($condition)) {
            $query->where($condition);
        }
        return $query->get();
    }


}
