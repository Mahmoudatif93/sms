<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Resource extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resources';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'version',
        'method',
        'uri',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    /**
     * Get the IAM policy definitions that reference this resource.
     */
    public function policyDefinitions(): BelongsToMany
    {
        return $this->belongsToMany(IamPolicyDefinition::class, 'iam_policy_definition_resource', 'resource_id', 'definition_id')
            ->using(IamPolicyDefinitionResource::class)
            ->withTimestamps();
    }

    public function resourceGroups(): BelongsToMany
    {
        return $this->belongsToMany(ResourceGroup::class, 'resource_group_resource')->withTimestamps();
    }

}
