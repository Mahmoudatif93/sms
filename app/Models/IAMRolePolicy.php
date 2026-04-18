<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class IAMRolePolicy extends Pivot
{
    protected $table = 'iam_role_policy';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['iam_role_id', 'iam_policy_id'];
}
