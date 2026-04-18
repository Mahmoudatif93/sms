<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserWebhook extends Model
{
    use HasFactory;
    protected $table = 'user_webhook'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'organization_id',
        'webhook_url','date'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }
}
