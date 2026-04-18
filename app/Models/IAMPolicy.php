<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property string $scope
 * @property int|null $created_at
 * @property int|null $updated_at
 * @method static Builder|IAMPolicy newModelQuery()
 * @method static Builder|IAMPolicy newQuery()
 * @method static Builder|IAMPolicy query()
 * @method static Builder|IAMPolicy whereCreatedAt($value)
 * @method static Builder|IAMPolicy whereDescription($value)
 * @method static Builder|IAMPolicy whereId($value)
 * @method static Builder|IAMPolicy whereName($value)
 * @method static Builder|IAMPolicy whereScope($value)
 * @method static Builder|IAMPolicy whereType($value)
 * @method static Builder|IAMPolicy whereUpdatedAt($value)
 * @property-read Collection<int, IAMPolicyDefinition> $definitions
 * @property-read int|null $definitions_count
 * @property string $organization_id
 * @method static Builder|IAMPolicy whereOrganizationId($value)
 * @mixin Eloquent
 */
class IAMPolicy extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'iam_policies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'description',
        'type',
        'scope',
        'organization_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the definitions for the IAM policy through the pivot table.
     */
    public function definitions(): BelongsToMany
    {
        return $this->belongsToMany(
            IAMPolicyDefinition::class,
            'iam_policy_definition_links',
            'iam_policy_id',
            'iam_policy_definition_id'
        )->using(IAMPolicyDefinitionLink::class)
            ->withTimestamps();
    }

    // Scope for allowed definitions
    // Define the scope for allowed definitions
    public function allowedDefinitions(): BelongsToMany
    {
        return $this->definitions()->where('effect', '=','allow');
    }
}
