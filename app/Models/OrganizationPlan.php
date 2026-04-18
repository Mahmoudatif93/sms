<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationPlan extends Model
{
    use HasFactory;
    use SoftDeletes;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'organization_plan';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'plan_id',
        'points_cnt',
        'price',
        'currency',
        'is_custom',
        'is_active',
    ];

    /**
     * Get the organization that owns the subscription.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the plan that owns the subscription.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Custom method to create a new organization plan connection
     *
     * @param string $organizationId
     * @param int $planId
     * @return self
     */
    public static function createConnection(string $organizationId, int $planId): self
    {
        return self::create([
            'organization_id' => $organizationId,
            'plan_id' => $planId
        ]);
    }

    /**
     * Scope a query to only include connections for a specific organization
     */
    public function scopeForOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope a query to only include connections for a specific plan
     */
    public function scopeForPlan($query, int $planId)
    {
        return $query->where('plan_id', $planId);
    }

    /**
     * Check if a connection exists between organization and plan
     */
    public static function hasConnection(string $organizationId, int $planId): bool
    {
        return self::where('organization_id', $organizationId)
                   ->where('plan_id', $planId)
                   ->exists();
    }


    /**
     * Remove connection between organization and plan
     */
    public static function removeConnection(string $organizationId, int $planId): bool
    {
        return self::where('organization_id', $organizationId)
                   ->where('plan_id', $planId)
                   ->delete();
    }
}
