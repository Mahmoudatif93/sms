<?php


namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $user_id
 * @property int $iam_role_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @method static Builder|IAMRoleUser newModelQuery()
 * @method static Builder|IAMRoleUser newQuery()
 * @method static Builder|IAMRoleUser query()
 * @method static Builder|IAMRoleUser whereCreatedAt($value)
 * @method static Builder|IAMRoleUser whereIamRoleId($value)
 * @method static Builder|IAMRoleUser whereId($value)
 * @method static Builder|IAMRoleUser whereUpdatedAt($value)
 * @method static Builder|IAMRoleUser whereUserId($value)
 * @property-read IAMRole|null $IAMRole
 * @mixin Eloquent
 */
class IAMRoleUser extends Pivot
{
    use SoftDeletes;

    protected $table = 'iam_role_user';

    protected $fillable = [
        'user_id',
        'iam_role_id',
        'organization_id',
        'billing_frequency',
        'billing_cycle_end',
        'is_billing_active',
        'wallet_id',
    ];

    protected $casts = [
        'billing_cycle_end' => 'datetime',
        'is_billing_active' => 'boolean',
    ];

    // If needed, define relationships back to User and IamRole

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function IAMRole(): BelongsTo
    {
        return $this->belongsTo(IAMRole::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Check if billing has expired.
     */
    public function isBillingExpired(): bool
    {
        if (!$this->is_billing_active || !$this->billing_cycle_end) {
            return true;
        }

        return $this->billing_cycle_end->isPast();
    }
}
