<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 *
 *
 * @property int $id
 * @property string $organization_id
 * @property int $user_id
 * @property string $status
 * @property boolean $has_special_wallet
 * @property boolean $auto_translation_enabled
 * @property array|null $preferred_languages
 * @property string|null $invite_token
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read Organization $organization
 * @property-read User $user
 * @method static Builder|OrganizationUser newModelQuery()
 * @method static Builder|OrganizationUser newQuery()
 * @method static Builder|OrganizationUser query()
 * @method static Builder|OrganizationUser whereCreatedAt($value)
 * @method static Builder|OrganizationUser whereId($value)
 * @method static Builder|OrganizationUser whereInviteToken($value)
 * @method static Builder|OrganizationUser whereOrganizationId($value)
 * @method static Builder|OrganizationUser whereStatus($value)
 * @method static Builder|OrganizationUser whereUpdatedAt($value)
 * @method static Builder|OrganizationUser whereUserId($value)
 * @property-read \App\Models\User|null $users
 * @mixin Eloquent
 */
class OrganizationUser extends Pivot
{
    use SoftDeletes;

    protected $table = 'organization_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'organization_id',
        'user_id',
        'status',
        'invite_token',
        'has_special_wallet',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the organization associated with this pivot.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user associated with this pivot.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the user's invitation is still pending.
     *
     * @return bool
     */
    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    /**
     * Mark the user's status as active.
     */
    public function activate(): void
    {
        $this->update(['status' => 'active']);
    }

    /**
     * Get all of the wallets for the model.
     *
     * This function defines a polymorphic one-to-many relationship between the current model
     * and the Wallet model. It allows the current model to have multiple Wallet instances
     * associated with it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */

    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'wallettable');
    }

    /**
     * Get all of the wallet assignments for the model.
     *
     * This function defines a polymorphic one-to-many relationship between the current model
     * and the WalletAssignment model. It allows the current model to have multiple WalletAssignment
     * instances associated with it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function walletAssignments()
    {
        return $this->morphMany(WalletAssignment::class, 'assignable');
    }
}
