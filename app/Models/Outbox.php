<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Outbox extends Model
{
    use HasFactory;
    protected $table = 'outbox';
    const CREATED_AT = 'creation_datetime';
    const UPDATED_AT = 'updated_time';

    protected $fillable =[
            'channel' ,
            'user_id' ,
            'message_id',
            'text' ,
            'count' ,
            'cost' ,
            'creation_datetime' ,
            'sending_datetime' ,
            'repeation_period' ,
            'repeation_times' ,
            'variables_message' ,
            'sender_name' ,
            'excel_file_numbers' ,
            'all_numbers' ,
            'number_index' ,
            'encrypted' ,
            'auth_code' ,
            'advertising' ,
    ];

    public static function get_message_by_message_id($message_id) {
        $message = self::where(['message_id' => $message_id])
            ->first();
        return $message;
    }

    public function message()
    {
        return $this->belongsTo(Message::class, 'message_id', 'id');
    }

}
