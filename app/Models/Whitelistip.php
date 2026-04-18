<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Whitelistip extends Model
{
    use HasFactory;
    protected $table = 'whitelist_ip'; // Replace with your actual table name

    public $timestamps = false;

    protected $fillable = [
        'user_id', 'organization_id',
        'name','ip'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }
}
