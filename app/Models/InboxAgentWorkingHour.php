<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Class InboxAgentWorkingHour
 *
 * Represents an inbox agent's working hours.
 *
 * @package App\Models
 * @property int $id Primary key
 * @property int $inbox_agent_id Foreign key to the user who is an inbox agent
 * @property string $day The day of the week (Monday, Tuesday, etc.)
 * @property string|null $start_time The start time of working hours (HH:MM:SS)
 * @property string|null $end_time The end time of working hours (HH:MM:SS)
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 * @property Carbon|null $deleted_at Timestamp when the record was soft-deleted (null if not deleted)
 *
 * @property-read User $agent The associated user who is an inbox agent
 *
 * @method static Builder|InboxAgentWorkingHour newModelQuery()
 * @method static Builder|InboxAgentWorkingHour newQuery()
 * @method static Builder|InboxAgentWorkingHour query()
 * @method static Builder|InboxAgentWorkingHour whereId($value)
 * @method static Builder|InboxAgentWorkingHour whereInboxAgentId($value)
 * @method static Builder|InboxAgentWorkingHour whereDay($value)
 * @method static Builder|InboxAgentWorkingHour whereStartTime($value)
 * @method static Builder|InboxAgentWorkingHour whereEndTime($value)
 * @method static Builder|InboxAgentWorkingHour whereCreatedAt($value)
 * @method static Builder|InboxAgentWorkingHour whereUpdatedAt($value)
 * @method static Builder|InboxAgentWorkingHour onlyTrashed()
 * @method static Builder|InboxAgentWorkingHour withTrashed()
 * @method static Builder|InboxAgentWorkingHour withoutTrashed()
 *
 * @mixin Eloquent
 */
class InboxAgentWorkingHour extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inbox_agent_working_hours';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['inbox_agent_id', 'day', 'start_time', 'end_time'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    const WORKDAYS = [
        0 => "Sunday",
        1 => "Monday",
        2 => "Tuesday",
        3 => "Wednesday",
        4 => "Thursday",
        5 => "Friday",
        6 => "Saturday"
    ];



    /**
     * Get the inbox agent (user) associated with these working hours.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inbox_agent_id');
    }
}
