<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

abstract class BaseMessageDetails extends Model
{
    use HasFactory;
    protected $fillable = ['message_id', 'text', 'length', 'number', 'country_id', 'operator_id', 'cost', 'response', 'status', 'gateway_id', 'key', 'encrypted', 'blocked', 'prvent_repeate', 'smpp_response', 'smpp_company', 'smpp_response_v2', 'send_dlr', 'count_try_send_dlr'];

    abstract  public static function getNumbers($message_id, $limit);

    public static function SendByNumbers($message_id,$numbers,$gateway_id,$res){
    
        self::where('message_id',$message_id)
            ->whereIn('number',$numbers)
            ->update(['gateway_id'=>$gateway_id,'response'=>$res,'status'=>2]);
          
    }

    public static function BackNumber($message_id,$numbers){
        self::where('message_id',$message_id)
            ->whereIn('number',$numbers)
            ->update(['status'=>0]);
    }
}
