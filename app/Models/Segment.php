<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Segment extends Model
{

    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'segments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'organization_id',
        'description',
    ];

    protected $casts = ['created_at' => 'timestamp', 'updated_at' => 'timestamp'];

    /**
     * @return BelongsTo
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ContactEntity::class, 'contact_segment', 'segment_id', 'contact_id'); //
    }

    /**
     * Relationship: A Segment has many Rules.
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SegmentRule::class, 'segment_id');
    }




}
