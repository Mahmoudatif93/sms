<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property int $business_manager_account_id References the Business Manager Account it belongs to.
 * @property string $access_token Business Integration System User Access Token
 * @property string $token_type Type of the token, e.g., "bearer"
 * @property int $expires_in Time in seconds until the token expires
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BusinessManagerAccount $businessManagerAccount
 * @method static Builder|BusinessIntegrationSystemUserAccessToken newModelQuery()
 * @method static Builder|BusinessIntegrationSystemUserAccessToken newQuery()
 * @method static Builder|BusinessIntegrationSystemUserAccessToken query()
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereAccessToken($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereBusinessManagerAccountId($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereCreatedAt($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereExpiresIn($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereId($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereTokenType($value)
 * @method static Builder|BusinessIntegrationSystemUserAccessToken whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class BusinessIntegrationSystemUserAccessToken extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business_integration_system_user_access_tokens';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'business_manager_account_id',
        'access_token',
        'token_type',
        'expires_in',
    ];

    /**
     * Get the business manager account that owns the token.
     * @return BelongsTo
     */
    public function businessManagerAccount(): BelongsTo
    {
        return $this->belongsTo(BusinessManagerAccount::class, 'business_manager_account_id');
    }

    /**
     * Check if the token is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return now()->gt($this->created_at->addSeconds($this->expires_in));
    }
}
