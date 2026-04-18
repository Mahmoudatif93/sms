<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id The business account ID.
 * @property int $user_id
 * @property string $name The name of the business.
 * @property string|null $link URI for business profile page.
 * @property string|null $profile_picture_uri The profile picture URI of the business.
 * @property string $two_factor_type The two-factor type authentication used for this business.
 * @property string $verification_status Verification status for this business.
 * @property string|null $vertical The vertical industry that this business associates with, or belongs to.
 * @property int|null $vertical_id The ID for the vertical industry.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @method static Builder|BusinessManagerAccount newModelQuery()
 * @method static Builder|BusinessManagerAccount newQuery()
 * @method static Builder|BusinessManagerAccount query()
 * @method static Builder|BusinessManagerAccount whereCreatedAt($value)
 * @method static Builder|BusinessManagerAccount whereId($value)
 * @method static Builder|BusinessManagerAccount whereLink($value)
 * @method static Builder|BusinessManagerAccount whereName($value)
 * @method static Builder|BusinessManagerAccount whereProfilePictureUri($value)
 * @method static Builder|BusinessManagerAccount whereTwoFactorType($value)
 * @method static Builder|BusinessManagerAccount whereUpdatedAt($value)
 * @method static Builder|BusinessManagerAccount whereUserId($value)
 * @method static Builder|BusinessManagerAccount whereVerificationStatus($value)
 * @method static Builder|BusinessManagerAccount whereVertical($value)
 * @method static Builder|BusinessManagerAccount whereVerticalId($value)
 * @property-read Collection<int, WhatsappBusinessAccount> $whatsappBusinessAccounts
 * @property-read int|null $whatsapp_business_accounts_count
 * @property-read Collection<int, MetaPage> $metaPages
 * @property-read int|null $meta_pages_count
 * @mixin Eloquent
 */
class BusinessManagerAccount extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'business_manager_accounts';
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * The type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'unsignedBigInteger';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    /// add user_id by migrations
    protected $fillable = [
        'id',
        'name',
        'link',
        'profile_picture_uri',
        'two_factor_type',
        'verification_status',
        'vertical',
        'vertical_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'two_factor_type' => 'string',
        'verification_status' => 'string',
        'vertical' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    /**
     * Get the Whatsapp Business Accounts owned by this business manager account.
     *
     * @return HasMany
     */

    public function whatsappBusinessAccounts(): HasMany
    {
        return $this->hasMany(WhatsappBusinessAccount::class, 'business_manager_account_id');
    }

    /**
     * Get all Meta Pages owned by this Business Manager.
     *
     * @return HasMany
     */
    public function metaPages(): HasMany
    {
        return $this->hasMany(MetaPage::class, 'business_manager_account_id');
    }


}
