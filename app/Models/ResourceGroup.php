<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * App\Models\ResourceGroup
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Resource[] $resources
 * @property-read int|null $resources_count
 * @method static Builder|ResourceGroup newModelQuery()
 * @method static Builder|ResourceGroup newQuery()
 * @method static Builder|ResourceGroup query()
 * @method static Builder|ResourceGroup whereCreatedAt($value)
 * @method static Builder|ResourceGroup whereDescription($value)
 * @method static Builder|ResourceGroup whereId($value)
 * @method static Builder|ResourceGroup whereName($value)
 * @method static Builder|ResourceGroup whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ResourceGroup extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'resource_groups';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'description'];

    /**
     * Get the resources that belong to this group.
     *
     * @return BelongsToMany
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_group_resource')->withTimestamps();
    }
}
