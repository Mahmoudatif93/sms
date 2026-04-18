<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\TemplateBodyDateTimeParameter
 *
 * Represents a date_time parameter within a WhatsApp template message body component.
 *
 * @property int $id The primary key of the date_time parameter.
 * @property int $template_message_body_component_id Foreign key to the body component that this date_time parameter belongs to.
 * @property string|null $fallback_value The fallback value for the date_time.
 * @property int|null $day_of_week Day of the week (1 = Monday, 7 = Sunday).
 * @property int|null $year The year value for the date_time parameter.
 * @property int|null $month The month value (1 to 12) for the date_time parameter.
 * @property int|null $day_of_month The day of the month value (1 to 31) for the date_time parameter.
 * @property int|null $hour The hour value (0 to 23) for the date_time parameter.
 * @property int|null $minute The minute value (0 to 59) for the date_time parameter.
 * @property string $calendar The calendar system used (default is GREGORIAN).
 * @property int|null $created_at The timestamp when the date_time parameter was created.
 * @property int|null $updated_at The timestamp when the date_time parameter was last updated.
 *
 * @property-read TemplateMessageBodyComponent|null $bodyComponent The body component associated with this date_time parameter.
 *
 * @method static Builder|TemplateBodyDateTimeParameter newModelQuery() Begin a new model query.
 * @method static Builder|TemplateBodyDateTimeParameter newQuery() Begin a new query for this model.
 * @method static Builder|TemplateBodyDateTimeParameter query() Get a new query builder for this model.
 *
 * @mixin Eloquent
 */
class TemplateBodyDateTimeParameter extends Model
{
    protected $table = 'template_body_date_time_parameters';

    protected $fillable = [
        'template_message_body_component_id',
        'fallback_value',
        'day_of_week',
        'year',
        'month',
        'day_of_month',
        'hour',
        'minute',
        'calendar',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the body component associated with this date_time parameter.
     *
     * @return BelongsTo
     */
    public function bodyComponent(): BelongsTo
    {
        return $this->belongsTo(TemplateMessageBodyComponent::class, 'template_message_body_component_id');
    }
}
