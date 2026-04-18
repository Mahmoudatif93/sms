<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class MessageTranslationBilling
 *
 * Represents the translation billing details for messages.
 *
 * @property int $id Primary key
 * @property int|null $messageable_id The ID of the polymorphic message (e.g., WhatsappMessage)
 * @property string|null $messageable_type The class name of the polymorphic message model
 * @property string $language The language of the translation
 * @property float $cost The cost of translation
 * @property bool $is_billed Indicates if the translation has been billed
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 * @property Carbon|null $deleted_at Timestamp when the record was soft deleted
 *
 * @property-read Model|MorphTo $messageable The polymorphic related model (e.g., WhatsappMessage)
 *
 * @method static Builder|MessageTranslationBilling newModelQuery()
 * @method static Builder|MessageTranslationBilling newQuery()
 * @method static Builder|MessageTranslationBilling query()
 * @method static Builder|MessageTranslationBilling whereId($value)
 * @method static Builder|MessageTranslationBilling whereMessageableId($value)
 * @method static Builder|MessageTranslationBilling whereMessageableType($value)
 * @method static Builder|MessageTranslationBilling whereLanguage($value)
 * @method static Builder|MessageTranslationBilling whereCost($value)
 * @method static Builder|MessageTranslationBilling whereIsBilled($value)
 * @method static Builder|MessageTranslationBilling whereCreatedAt($value)
 * @method static Builder|MessageTranslationBilling whereUpdatedAt($value)
 * @method static Builder|MessageTranslationBilling whereDeletedAt($value)
 * @method static Builder|MessageTranslationBilling onlyTrashed()
 * @method static Builder|MessageTranslationBilling withTrashed()
 * @method static Builder|MessageTranslationBilling withoutTrashed()
 *
 * @mixin Eloquent
 */
class MessageTranslationBilling extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'message_translation_billings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'messageable_id',
        'messageable_type',
        'language',
        'cost',
        'is_billed',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cost' => 'float',
        'is_billed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the related model for translation billing (e.g., WhatsappMessage).
     *
     * @return MorphTo
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }
}

