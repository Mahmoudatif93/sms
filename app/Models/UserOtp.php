<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserOtp extends Model
{
    use HasFactory;
    protected $table = 'user_otp'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'organization_id',
        'mobile'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

        // Function to get OTPs by user ID
        public static function getByUserId($userId)
        {
            return UserOtp::where('user_id', $userId)->get();
        }


}
