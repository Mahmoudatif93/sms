<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageStatistic extends Model
{
    use HasFactory;
    protected $table = 'message_statistics'; // Replace with your actual table name
    protected $fillable = ['user_id','workspace_id','all_numbers','all_numbers_json','sender_name',
    'message','send_time_method','send_time','sms_type','repeation_times',
    'excle_file','leng','cost','count'];

}
