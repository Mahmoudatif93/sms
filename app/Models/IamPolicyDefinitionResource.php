<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IamPolicyDefinitionResource extends Pivot
{
    public $timestamps = true;
    protected $table = 'iam_policy_definition_resource';

    // If you want to include custom timestamps
    protected $fillable = [
        'definition_id',
        'resource_id',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
