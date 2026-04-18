<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactGroup extends Model
{

    protected $table = 'contact_group'; // Replace with your actual table name

    use HasFactory;

    protected $fillable = [
        'id',
        'user_id',
        'group_id',
        'name',
        'note',
        'view_numbers'
    ];
    protected $primaryKey = 'id';

    public function user()
    {
        return $this->hasMany(User::class);
    }
    public static function get_contact_groups($user_id)
    {
        $res = array();
        $entries = ContactGroup::where('user_id', $user_id)
            ->orderBy('group_id')
            ->get();
        foreach ($entries as $entry) {

            if (empty($entry['group_id'])) {
                $entry['count'] = Contact::get_cnt_by_group_id($entry['id']);
                $entry['groups'] = array();

                if ($entry['view_numbers'] == 1) {
                    $entry['numbers'] = Contact::get_by_group_id_user_id($entry['id'], $user_id, 0, 400);
                } else {
                    $entry['numbers'] = [];
                }
                $res[$entry['id']] = $entry;
            } else {
                $entry['count'] = Contact::get_cnt_by_group_id($entry['id']);
                if ($entry['view_numbers'] == 1) {
                    $entry['numbers'] = Contact::get_by_group_id_user_id($entry['id'], $user_id, 0, 400);
                } else {
                    $entry['numbers'] = [];
                }
                $res[$entry['group_id']]['groups'] = $entry;
                //dd($res);
            }
        }
        return $res;
    }

    public static function get_granted_contact_groups($user_id)
    {
        $res = array();
        $ids = user::where('id', $user_id)->first()->granted_group_ids;
        $parent_id = user::where('id', $user_id)->first()->parent_id;
        if (!empty($ids)) {

            $entries = ContactGroup::where('user_id', $parent_id)
                ->whereIn('id', $ids)
                ->get();
        } else {
            $entries = ContactGroup::where('user_id', $parent_id)
                ->get();
        }


        foreach ($entries as $entry) {
            $entry['count'] = Contact::get_distinct_cnt_by_group_id($entry['id']);
            $res[$entry['id']] = $entry;
        }
        return $res;
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'group_id', 'id');
    }


    public static function getUserContactGroups($groupId = 0, $user_id)
    {
        return self::where([
            'user_id' => $user_id,
            'group_id' => $groupId
        ])
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    public static function getUserContactGroupsWithpagnate($perPage = 15, $user_id, $search = null)
    {
        if ($search != null) {
            return self::where('user_id', $user_id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('note', 'like', '%' . $search . '%');
                })
                ->withCount('contacts')
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        } else {
            return self::where('user_id', $user_id)
                ->withCount('contacts')
                ->orderBy('created_at', 'DESC')
                ->paginate($perPage);
        }
    }


    public static function getUserContactGroupsWithContactCount($parent_id, $search = null)
    {

        if ($search != null) {
            return self::where('user_id', $parent_id)
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('note', 'like', '%' . $search . '%');
                })
                ->withCount('contacts')
                ->orderBy('created_at', 'DESC')
                ->get();
        } else {
            return self::where('user_id', $parent_id)
                ->withCount('contacts')
                ->orderBy('created_at', 'DESC')
                ->get();
        }
    }
    public static function get_by_group_id($group_id)
    {
        return ContactGroup::where('group_id', $group_id)->get();
    }
}
