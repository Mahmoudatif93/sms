<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SegmentRule extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'segment_rules';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'segment_id',
        'attribute_definition_id',
        'operator',
        'value',
    ];

    /**
     * Relationship: A Rule belongs to a Segment.
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class, 'segment_id');
    }

    /**
     * Relationship: A Rule belongs to an AttributeDefinition.
     */
    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'attribute_definition_id');
    }
}
