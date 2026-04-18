<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFailedLogin extends Model
{
    use HasFactory;

    protected $table = 'user_failed_login';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'password',
        'ip',
        'date',
        'status',
    ];

    // Define relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public static function InsertByArray($data)
    {
        // Use the create method to insert the data into the database
        return self::create($data);
    }
}

