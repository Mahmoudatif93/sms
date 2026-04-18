<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IAMPolicyDefinitionLink extends Pivot
{
    protected $table = 'iam_policy_definition_links';

    protected $fillable = [
        'iam_policy_id',
        'iam_policy_definition_id',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];
}
