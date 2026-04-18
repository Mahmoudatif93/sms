<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * App\Models\AttributeDefinition
 *
 * @property string $id
 * @property string|null $organization_id
 * @property string $key
 * @property string $display_name
 * @property string $cardinality
 * @property string $type
 * @property bool $pii
 * @property bool $read_only
 * @property bool $builtin
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Organization|null $organization
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SegmentRule> $segmentRules
 * @property-read int|null $segment_rules_count
 *
 * @method static Builder|AttributeDefinition newModelQuery()
 * @method static Builder|AttributeDefinition newQuery()
 * @method static Builder|AttributeDefinition query()
 * @method static Builder|AttributeDefinition whereId($value)
 * @method static Builder|AttributeDefinition whereOrganizationId($value)
 * @method static Builder|AttributeDefinition whereKey($value)
 * @method static Builder|AttributeDefinition whereDisplayName($value)
 * @method static Builder|AttributeDefinition whereCardinality($value)
 * @method static Builder|AttributeDefinition whereType($value)
 * @method static Builder|AttributeDefinition wherePii($value)
 * @method static Builder|AttributeDefinition whereReadOnly($value)
 * @method static Builder|AttributeDefinition whereBuiltin($value)
 * @method static Builder|AttributeDefinition whereCreatedAt($value)
 * @method static Builder|AttributeDefinition whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class AttributeDefinition extends Model
{
    const NAME_KEY = 'name';
    const DISPLAY_NAME_KEY = 'display-name';

    public $incrementing = false;
    protected $table = 'attribute_definitions';
    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'organization_id',
        'key',
        'display_name',
        'cardinality',
        'type',
        'pii',
        'read_only',
        'builtin'
    ];

    protected $casts = [
        'pii' => 'boolean',
        'read_only' => 'boolean',
        'builtin' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Organization this definition belongs to (null if builtin).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Segment rules linked to this attribute definition.
     */
    public function segmentRules(): HasMany
    {
        return $this->hasMany(SegmentRule::class, 'attribute_definition_id');
    }

    /**
     * Scope: get built-in definitions.
     */
    public function scopeBuiltin(Builder $query): Builder
    {
        return $query->where('builtin', true)->whereNull('organization_id');
    }

    /**
     * Scope: get organization-specific + global definitions.
     */
    public function scopeForOrgOrGlobal(Builder $query, string $organizationId): Builder
    {
        return $query->where(function ($q) use ($organizationId) {
            $q->where('organization_id', $organizationId)
                ->orWhereNull('organization_id');
        });
    }
}
