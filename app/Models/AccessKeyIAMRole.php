<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class AccessKeyIAMRole extends Pivot
{
    protected $table = 'access_key_iam_role';

    // Specify the fillable attributes
    protected $fillable = [
        'access_key_id',
        'iam_role_id',
        'type',
    ];

    public $timestamps = true; // Enable timestamps
}
