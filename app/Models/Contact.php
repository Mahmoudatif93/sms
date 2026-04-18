<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contact extends Model
{

    use HasFactory;
    protected $table = 'contact'; // Replace with your actual table name

    protected $fillable = [
        'user_id',
        'group_id',
        'number',
        'name'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public static function get_cnt_by_group_id($group_id)
    {

        $cnt = DB::table('contact')
            ->where('group_id', $group_id)
            ->count();
        return $cnt;
    }

    public static function get_by_group_id_user_id($group_id, $user_id, $offset, $limit)
    {
        $results = DB::table('contact')
            ->where('group_id', $group_id)
            ->where('user_id', $user_id)
            ->orderBy('name')
            ->offset($offset)
            ->limit($limit)
            ->get();
        return  $results;
    }


    public static function get_unclassified_cnt_by_user_id($user_id)
    {

        $result = Contact::selectRaw('count(number) as cnt')
            ->where('user_id', $user_id)
            ->where('group_id', 0)
            ->first();


        return   $result->cnt;
    }

    public static function get_distinct_cnt_by_group_id($group_id)
    {

        $result = Contact::selectRaw('count(distinct number) as cnt')
            ->where('group_id', $group_id)
            ->first();
        return   $result->cnt;
    }

    public function contactGroup(): BelongsTo
    {
        return $this->belongsTo(ContactGroup::class, 'group_id', 'id');
    }
    public static function getUserContacts($perPage = 15, $user_id, $search = null)
    {
        if ($search != null) {
            return self::where('user_id', $user_id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('number', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        } else {
            return self::where('user_id', $user_id)
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        }
    }

    public static function getallUserContacts($user_id, $search = null)
    {

        if ($search != null) {
            return self::where('user_id', $user_id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('number', 'like', '%' . $search . '%');
                })
                ->orderBy('created_at', 'DESC')
                ->get();
        } else {
            return self::where('user_id', $user_id)
                ->orderBy('created_at', 'DESC')
                ->get();
        }
    }
}
