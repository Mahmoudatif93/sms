<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use DB;

class BalanceTransfer extends Model
{
    use HasFactory;
    protected $table = 'balance_transfer'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'id', 'sender_id', 'receiver_id',
        'points_cnt', 'date'
    ];
    protected $primaryKey = 'id';

    public function sender()
    {
        return $this->hasMany(User::class, 'id', 'sender_id');
    }

    public function receiver()
    {
        return $this->hasMany(User::class, 'id', 'receiver_id');
    }

    public function getIdByUsername($username)
    {
        // Find the user by username
        $user = User::where('username', $username)->first();
        // Check if the user was found
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        // Return the user's ID
        return $user->id;
    }




}
