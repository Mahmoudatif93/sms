<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminFailedLogin extends Model
{

    use HasFactory;
    protected $table = 'admin_failed_login'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'username',
        'password',
        'ip',
        'date',
    ];

    public static function InsertByArray($data)
    {
        // Use the create method to insert the data into the database
        return self::create($data);
    }

}
