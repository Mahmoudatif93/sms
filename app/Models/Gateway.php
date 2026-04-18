<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\Sms\SenderHelper;

class Gateway extends Model
{
    use HasFactory;
    protected $table = 'gateway';
    protected $fillable = [
        'name',
        'username',
        'password',
        'method',
        'root_path',
        'credit_url',
        'en_url',
        'en_unicode',
        'ar_url',
        'ar_unicode',
        'max_number',
        'plus_sign',
        'encode_plus_sign',
        'double_enter',
        'detailed_result',
        'response_type',
        'status'
    ];

    public function url($is_variable,$senderName){
   
        if($is_variable){
           return SenderHelper::isAdSender($senderName)? "https://bfilter.dreams.sa/api/sms/variable?username=*USERNAME*&password=*PASSWORD*&message=*MESSAGE*&numbers=*MOBILENO*&sender=*SENDERID*&unicode=0&return=full&prevent_repeat=0&period=0&message_id=*MESSAGEID*&is_hlr=*IS_HLR*" :"https://wfilter.dreams.sa/api/sms/variablev2?username=*USERNAME*&password=*PASSWORD*&message=*MESSAGE*&numbers=*MOBILENO*&sender=*SENDERID*&unicode=0&return=full&prevent_repeat=0&period=0&message_id=*MESSAGEID*&is_hlr=*IS_HLR*";
        }
        return $this->en_url;
    }
}
