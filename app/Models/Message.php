<?php
namespace App\Models;

use App\Helpers\Sms\EncryptionHelper;
use DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Message extends BaseMessage
{
    use HasFactory, SoftDeletes;
    protected $table = 'message';
    const CREATED_AT = 'creation_datetime';
    const UPDATED_AT = 'updation_datetime';
    protected $dates = ['deleted_at'];

    protected $fillable = [
        'channel',
        'user_id',
        'workspace_id',
        'text',
        'creation_datetime',
        'sending_datetime',
        'variables_message',
        'count',
        'cost',
        'sender_name',
        'length',
        'lang',
        'status',
        'deleted_by_user',
        'auth_code',
        'sent_cnt',
        'sent_cnt_v2',
        'proccess',
        'advertising',
        'result',
        'is_dlr',
        'response_info',
        'errorCode',
        'updation_datetime',
        'encrypted',
    ];

    public function getNumbers($limit)
    {
        if ($this->variables_message) {
            $numbers_texts       = MessageDetails::getNumberText($this->id, $limit);
            $numbers_texts_array = [];
            foreach ($numbers_texts as $number_text) {
                $numbers_texts_array[] = ['to' => $number_text['number'], 'message' => $number_text['text']];
            }
            return $numbers_texts_array;
        } else {
            return MessageDetails::getNumbers($this->id, $limit);
        }
    }

    protected static function getDetailsModel()
    {
        return new MessageDetails();
    }

    public function MessageDetails()
    {
        return $this->hasMany(MessageDetails::class);
    }

    public function getStatusAttribute($value)
    {
        return $value == 0 ? __('message.pending')
        : ($value == 1 ? __('message.under_processing')
            : ($value == 2 ? __('message.sent')
                : __('message.unknown')));
    }

    public static function RevisionMessage($user_id, $perPage, $search)
    {
        // Base query
        $query = Message::with('user')->where('advertising', 1)
            ->where('user_id', $user_id)->where('status', 0)
            ->where('deleted_by_user', 0);
        // Add search filters if provided
        if (! empty($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('sender_name', 'like', '%' . $search . '%')
                    ->orWhere('count', 'like', '%' . $search . '%')
                    ->orWhere('text', 'like', '%' . $search . '%')
                    ->orWhere('status', 'like', '%' . $search . '%')
                    ->orWhere('channel', 'like', '%' . $search . '%')
                    ->orWhere('cost', 'like', '%' . $search . '%');
            });
            $query->orWhereHas('user', function ($query) use ($search) {
                $query->where('username', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });

        }
        $messages = $query->where('user_id', $user_id);
        $messages = $query->orderBy('id', 'DESC');
        // Retrieve messages with or without pagination
        return $perPage ? $messages = $query->paginate($perPage) : $messages = $query->get();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->hasMany(MessageDetails::class, 'message_id', 'id');
    }

    public static function revision($perPage, $search)
    {
        $query = Message::where('advertising', 1)->where('deleted_by_user', 0)->where('status', 0);
        if (! empty($search)) {
            $query->where(function ($query) use ($search) {
                $query->where('sender_name', 'like', '%' . $search . '%')
                    ->orWhere('text', 'like', '%' . $search . '%');
            });
        }
        return $query->orderBy('id', 'DESC')->paginate($perPage);
    }
    public static function RevisionMessageDetails($message_id, $perPage, $search)
    {
        // Base query with eager loading
        $query = Message::with('details')
            ->where('advertising', 1)
            ->where('deleted_by_user', 0)
            ->where('message.status', 0)
            ->when($message_id, function ($q) use ($message_id) {
                $q->where('message.id', '=', $message_id);
            });

        // Apply search filters
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sender_name', 'like', "%$search%")
                    ->orWhere('count', 'like', "%$search%")
                    ->orWhere('text', 'like', "%$search%")
                    ->orWhere('status', 'like', "%$search%")
                    ->orWhere('channel', 'like', "%$search%")
                    ->orWhere('cost', 'like', "%$search%")
                    ->orWhereHas('details', function ($q) use ($search) {
                        $q->where('smpp_company', 'like', "%$search%")
                            ->orWhere('number', 'like', "%$search%")
                            ->orWhere('status', 'like', "%$search%")
                            ->orWhere('text', 'like', "%$search%")
                            ->orWhere('cost', 'like', "%$search%");
                    });
            });
        }

        // Apply ordering
        $query->orderBy('id', 'DESC');

        // Retrieve messages with or without pagination
        return $perPage ? $query->paginate($perPage) : $query->get();
    }


    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }


    public function scopeFilter($query, $filters)
    {
        return $query->select('id','sender_name',
            DB::raw("text"),
            'channel',
            'status',
            'cost',
            'count',
            'sending_datetime',
            'creation_datetime', 'encrypted')
            ->where('deleted_by_user', 0)
            ->when($filters['from_date'] ?? null, function ($q) use ($filters) {
                $q->where('creation_datetime', '>=', $filters['from_date']);
            })
            ->when($filters['till_date'] ?? null, function ($q) use ($filters) {
                $q->where('creation_datetime', '<=', $filters['till_date']);
            })
            ->when($filters['sender_name'] ?? null, function ($q) use ($filters) {
                $q->where('sender_name', 'LIKE', "%{$filters['sender_name']}%");
            })
            ->when($filters['search'] ?? null, function ($q) use ($filters) {
                $q->where(function ($subQuery) use ($filters) {
                    $subQuery->where('text', 'LIKE', "%{$filters['search']}%")
                        ->orWhere('channel', 'LIKE', "%{$filters['search']}%");
                });
            })->OrderBy('id');
    }


    public function getTextAttribute($value)
    {
        if ($this->encrypted == 1) {
            try {
                return Crypt::decryptString($value);
            } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                $encrypt = new EncryptionHelper();
                return $encrypt->decrypt($value);
            }
        }
        return $value;
    }

}
