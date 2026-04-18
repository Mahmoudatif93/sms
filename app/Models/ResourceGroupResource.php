<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * App\Models\ResourceGroupResource
 *
 * @property int $id
 * @property int $resource_group_id
 * @property int $resource_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|ResourceGroupResource newModelQuery()
 * @method static Builder|ResourceGroupResource newQuery()
 * @method static Builder|ResourceGroupResource query()
 * @method static Builder|ResourceGroupResource whereCreatedAt($value)
 * @method static Builder|ResourceGroupResource whereId($value)
 * @method static Builder|ResourceGroupResource whereResourceGroupId($value)
 * @method static Builder|ResourceGroupResource whereResourceId($value)
 * @method static Builder|ResourceGroupResource whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ResourceGroupResource extends Pivot
{
    /**
     * The table associated with the pivot model.
     *
     * @var string
     */
    protected $table = 'resource_group_resource';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'resource_group_id',
        'resource_id',
    ];
}
