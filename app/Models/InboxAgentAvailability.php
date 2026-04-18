<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class InboxAgentAvailability
 *
 * Represents an inbox agent's availability status and timezone.
 *
 * @package App\Models
 * @property int $id Primary key
 * @property int $inbox_agent_id Foreign key to the user who is an inbox agent
 * @property string|null $timezone The agent's timezone (e.g., "America/New_York")
 * @property string $availability The agent's availability status ("active", "away", "out_of_office")
 * @property int|null $created_at Unix timestamp when the record was created
 * @property int|null $updated_at Unix timestamp when the record was last updated
 * @property int|null $deleted_at Unix timestamp when the record was soft-deleted (null if not deleted)
 *
 * @property-read User $agent The associated user who is an inbox agent
 *
 * @method static Builder|InboxAgentAvailability newModelQuery()
 * @method static Builder|InboxAgentAvailability newQuery()
 * @method static Builder|InboxAgentAvailability query()
 * @method static Builder|InboxAgentAvailability whereId($value)
 * @method static Builder|InboxAgentAvailability whereInboxAgentId($value)
 * @method static Builder|InboxAgentAvailability whereTimezone($value)
 * @method static Builder|InboxAgentAvailability whereAvailability($value)
 * @method static Builder|InboxAgentAvailability whereCreatedAt($value)
 * @method static Builder|InboxAgentAvailability whereUpdatedAt($value)
 * @method static Builder|InboxAgentAvailability onlyTrashed()
 * @method static Builder|InboxAgentAvailability withTrashed()
 * @method static Builder|InboxAgentAvailability withoutTrashed()
 *
 * @mixin Eloquent
 */
class InboxAgentAvailability extends Model
{
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'inbox_agent_availabilities';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['inbox_agent_id', 'timezone', 'availability'];

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

    /**
     * Get the inbox agent (user) associated with this availability.
     *
     * @return BelongsTo
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inbox_agent_id');
    }
}
