<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class MessageBilling
 *
 * Represents unified billing details for messages (translation, chatbot, AI, etc.).
 *
 * @property int $id Primary key
 * @property int|null $messageable_id The ID of the polymorphic message (e.g., WhatsappMessage)
 * @property string|null $messageable_type The class name of the polymorphic message model
 * @property string $type The type of billing (translation, chatbot, ai, etc.)
 * @property float $cost The cost of the service
 * @property bool $is_billed Indicates if the service has been billed
 * @property array|null $metadata Additional data (e.g., language for translation)
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 * @property Carbon|null $deleted_at Timestamp when the record was soft deleted
 *
 * @property-read Model|MorphTo $messageable The polymorphic related model (e.g., WhatsappMessage)
 *
 * @method static Builder|MessageBilling newModelQuery()
 * @method static Builder|MessageBilling newQuery()
 * @method static Builder|MessageBilling query()
 * @method static Builder|MessageBilling whereId($value)
 * @method static Builder|MessageBilling whereMessageableId($value)
 * @method static Builder|MessageBilling whereMessageableType($value)
 * @method static Builder|MessageBilling whereType($value)
 * @method static Builder|MessageBilling whereCost($value)
 * @method static Builder|MessageBilling whereIsBilled($value)
 * @method static Builder|MessageBilling whereMetadata($value)
 * @method static Builder|MessageBilling whereCreatedAt($value)
 * @method static Builder|MessageBilling whereUpdatedAt($value)
 * @method static Builder|MessageBilling whereDeletedAt($value)
 * @method static Builder|MessageBilling onlyTrashed()
 * @method static Builder|MessageBilling withTrashed()
 * @method static Builder|MessageBilling withoutTrashed()
 *
 * @mixin Eloquent
 */
class MessageBilling extends Model
{
    use SoftDeletes;

    // Billing types
    const TYPE_TRANSLATION = 'translation';
    const TYPE_CHATBOT = 'chatbot';
    const TYPE_AI = 'ai';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'message_billings';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'messageable_id',
        'messageable_type',
        'type',
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
     * Get the related model for billing (e.g., WhatsappMessage).
     *
     * @return MorphTo
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope to filter by billing type.
     *
     * @param Builder $query
     * @param string $type
     * @return Builder
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get translation billings.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeTranslation(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_TRANSLATION);
    }

    /**
     * Scope to get chatbot billings.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeChatbot(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_CHATBOT);
    }

    /**
     * Scope to get unbilled records.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeUnbilled(Builder $query): Builder
    {
        return $query->where('is_billed', false);
    }

    /**
     * Scope to get billed records.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeBilled(Builder $query): Builder
    {
        return $query->where('is_billed', true);
    }
}
