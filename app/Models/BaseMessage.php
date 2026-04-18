<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

abstract class BaseMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel',
        'user_id',
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
        'auth_code',
        'advertising',
        'sent_cnt',
        'deleted_by_user'
    ];

    abstract public function getNumbers($limit);

    public static function refreshStatus($id)
    {
        $message = self::where('id', $id)->first();
        if ($message) {
            $sent_count = static::getDetailsModel()->where(['message_id' => $id, 'status' => 2])->count();
            $message->update(['sent_cnt' => $sent_count]);

            if ($message->sent_cnt >= $message->count) {
                $message->finished();
                $message->encrypt();
            }
        }
    }

    public function encrypt()
    {
        self::where('id', $this->id)
            ->update(['encrypted' => 1, 'text' => Crypt::encryptString($this->text)]);
    }

    public function proccess()
    {
        $this->proccess = 1;
        $this->save();
    }

    public function finished()
    {
        $this->status = 2;
        $this->save();
    }
    public function unProccess()
    {
        $this->proccess = 0;
        $this->save();
    }

    abstract protected static function getDetailsModel();

}
