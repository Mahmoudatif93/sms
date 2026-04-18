<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\ContactAttribute
 *
 * @property int $id
 * @property string $contact_id
 * @property string $attribute_definition_id
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ContactEntity $contact
 * @property-read AttributeDefinition $attributeDefinition
 * @method static Builder|ContactAttribute newModelQuery()
 * @method static Builder|ContactAttribute newQuery()
 * @method static Builder|ContactAttribute query()
 * @method static Builder|ContactAttribute whereContactId($value)
 * @method static Builder|ContactAttribute whereAttributeDefinitionId($value)
 * @method static Builder|ContactAttribute whereValue($value)
 * @method static Builder|ContactAttribute whereCreatedAt($value)
 * @method static Builder|ContactAttribute whereUpdatedAt($value)
 * @mixin Eloquent
 */
class ContactAttribute extends Model
{
    // Specify the table name if it's not the default plural of the model name
    protected $table = 'contact_attributes';

    // Define which attributes can be mass assigned
    protected $fillable = [
        'contact_id',
        'attribute_definition_id',
        'value',
    ];

    /**
     * Relationship: Each ContactAttribute belongs to a Contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    /**
     * Relationship: Each ContactAttribute belongs to an AttributeDefinition.
     */
    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class, 'attribute_definition_id');
    }
}
