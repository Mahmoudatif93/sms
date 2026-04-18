<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;


/**
 * App\Models\OrganizationMembershipPlan
 *
 * @property int $id
 * @property string $organization_id
 * @property int $service_id
 * @property float|null $price
 * @property string $frequency
 * @property string $status
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Service $service
 *
 * @mixin Eloquent
 */
class OrganizationMembershipPlan extends Model
{
    protected $table = 'organization_membership_plans';

    protected $appends = ['status'];

    protected $fillable = [
        'organization_id',
        'service_id',
        'currency',
        'price',
        'frequency',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'timestamp',
        'end_date' => 'timestamp',
        'price' => 'float',
    ];


    /**
     * Relationship with the Organization model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship with the Service model.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }


    /**
     * Helper: Is the plan monthly?
     */
    public function isMonthly(): bool
    {
        return $this->frequency === 'monthly';
    }

    /**
     * Helper: Is the plan yearly?
     */
    public function isYearly(): bool
    {
        return $this->frequency === 'yearly';
    }

    public function getStatusAttribute(): string
    {
        return $this->organization->isMembershipBillingActive($this) ? 'active' : 'inactive';
    }
}
