<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class IAMPolicyDefinition extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'iam_policy_definitions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'effect',
        'action',
        'resource_id'
    ];

    /**
     * Get the policies associated with this definition through the pivot table.
     */
    public function policies(): BelongsToMany
    {
        return $this->belongsToMany(
            IAMPolicy::class,
            'iam_policy_definition_links',
            'iam_policy_definition_id',
            'iam_policy_id'
        )->using(IAMPolicyDefinitionLink::class)
            ->withTimestamps();
    }

    /**
     * Get the resource associated with this IAM Policy Definition.
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}
