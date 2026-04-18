<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BadWordsLog extends Model
{
    use HasFactory;
    protected $table = 'badwords_log'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'badword',
        'user_id',
        'date'
    ];
}
