<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;
    public $timestamps = true;

    protected $fillable = [
        'user_id',
        'otp',
        'expires_at',
        'type'
    ];

    public static function getOtpRecord($otp, $user_id,$type)
    {
        return self::where(['otp' => $otp, 'user_id' => $user_id,'type'=>$type])->first();
    }

  public static function createOtpRecord($user_id,$otp,$time,$type)
    {
        Otp::create([
            'user_id' => $user_id,
            'otp' => $otp,
            'expires_at' => $time,
            'type'=>$type
        ]);
    }


    public static function getByUserId($userId)
    {
        return UserOtp::where('user_id', $userId)->get();
    }
}
