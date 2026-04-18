<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordHistory extends Model
{
    protected $table = 'password_history'; // Replace with your actual table name
    public $timestamps = false;


    protected $fillable = ['id','user_id', 'password'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
