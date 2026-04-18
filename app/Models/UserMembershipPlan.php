<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\UserMembershipPlan
 *
 * @property int $id
 * @property int $user_id Foreign key to the users table
 * @property int $service_id Foreign key to the services table
 * @property float $price Cost of the membership
 * @property string $frequency Billing frequency (e.g., monthly, yearly)
 * @property string $status Status of the membership (e.g., active, cancelled)
 * @property Carbon $start_date Start date of the plan
 * @property Carbon|null $end_date End date of the plan (nullable)
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 *
 * @property-read User $user The user associated with this membership plan
 * @property-read Service $service The service associated with this membership plan
 *
 * @method static Builder|UserMembershipPlan newModelQuery()
 * @method static Builder|UserMembershipPlan newQuery()
 * @method static Builder|UserMembershipPlan query()
 * @method static Builder|UserMembershipPlan whereId($value)
 * @method static Builder|UserMembershipPlan whereUserId($value)
 * @method static Builder|UserMembershipPlan whereServiceId($value)
 * @method static Builder|UserMembershipPlan wherePrice($value)
 * @method static Builder|UserMembershipPlan whereFrequency($value)
 * @method static Builder|UserMembershipPlan whereStatus($value)
 * @method static Builder|UserMembershipPlan whereStartDate($value)
 * @method static Builder|UserMembershipPlan whereEndDate($value)
 * @method static Builder|UserMembershipPlan whereCreatedAt($value)
 * @method static Builder|UserMembershipPlan whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class UserMembershipPlan extends Model
{
    protected $table = 'user_membership_plans';

    protected $fillable = [
        'user_id',
        'service_id',
        'price',
        'frequency',
        'status',
        'start_date',
        'end_date',
    ];

    /**
     * Get the user that owns the membership plan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the service associated with the membership plan.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
