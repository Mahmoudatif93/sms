<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HlrLookupHis extends Model
{
    use HasFactory;
    protected $table = 'hlr_lookup_his'; // Replace with your actual table name

    protected $fillable = [
        'number',
        'live_status',
        'country',
        'telephone_number_type',
        'network',
        'roaming',
        'currentDate'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }
}
