<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, IAMPolicy> $policies
 * @property-read int|null $policies_count
 * @method static Builder|IAMRole newModelQuery()
 * @method static Builder|IAMRole newQuery()
 * @method static Builder|IAMRole query()
 * @method static Builder|IAMRole whereCreatedAt($value)
 * @method static Builder|IAMRole whereDescription($value)
 * @method static Builder|IAMRole whereId($value)
 * @method static Builder|IAMRole whereName($value)
 * @method static Builder|IAMRole whereOrganizationId($value)
 * @method static Builder|IAMRole whereType($value)
 * @method static Builder|IAMRole whereUpdatedAt($value)
 * @mixin Eloquent
 */
class IAMRole extends Model
{

    const INBOX_AGENT_ROLE = "Inbox Agent";
    const ORGANIZATION_OWNER = "Organization Manager";
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = "iam_roles";
    protected $fillable = ['organization_id', 'name', 'description', 'type'];

    /**
     * Get the organization that owns the role.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The policies that belong to the role.
     */
    public function policies()
    {
        return $this->belongsToMany(IAMPolicy::class, 'iam_role_policy', 'iam_role_id', 'iam_policy_id')
            ->withTimestamps();
    }
}
