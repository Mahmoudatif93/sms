<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 *
 *
 * @property int $id The Whatsapp Phone Number ID.
 * @property int $whatsapp_business_account_id The Whatsapp business account ID connected to it.
 * @property string|null $verified_name Verified name of the business associated with the phone number.
 * @property string|null $code_verification_status Status of the code verification.
 * @property string|null $display_phone_number The phone number displayed.
 * @property string $quality_rating Quality rating of the phone number.
 * @property string|null $platform_type Platform type of the phone number.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @method static Builder|WhatsappPhoneNumber newModelQuery()
 * @method static Builder|WhatsappPhoneNumber newQuery()
 * @method static Builder|WhatsappPhoneNumber query()
 * @method static Builder|WhatsappPhoneNumber whereCodeVerificationStatus($value)
 * @method static Builder|WhatsappPhoneNumber whereCreatedAt($value)
 * @method static Builder|WhatsappPhoneNumber whereDisplayPhoneNumber($value)
 * @method static Builder|WhatsappPhoneNumber whereId($value)
 * @method static Builder|WhatsappPhoneNumber wherePlatformType($value)
 * @method static Builder|WhatsappPhoneNumber whereQualityRating($value)
 * @method static Builder|WhatsappPhoneNumber whereUpdatedAt($value)
 * @method static Builder|WhatsappPhoneNumber whereVerifiedName($value)
 * @method static Builder|WhatsappPhoneNumber whereWhatsappBusinessAccountId($value)
 * @property-read WhatsappBusinessAccount $whatsappBusinessAccount
 * @property-read WhatsappBusinessProfile|null $whatsappBusinessProfile
 * @mixin Eloquent
 */
class WhatsappPhoneNumber extends Model  implements HasMedia
{

    use InteractsWithMedia;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_phone_numbers';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';


    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;


    /**
     * The type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'unsignedBigInteger';



    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'whatsapp_business_account_id',
        'verified_name',
        'code_verification_status',
        'display_phone_number',
        'quality_rating',
        'platform_type',
        'is_registered',
        'pin',
    ];

    /**
     * Get the WhatsApp business account associated with the phone number.
     *
     * @return BelongsTo
     */
    public function whatsappBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }

    public function whatsappBusinessProfile(): HasOne
    {
        return $this->hasOne(WhatsappBusinessProfile::class, 'whatsapp_phone_number_id');
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'whatsapp_phone_number_id');
    }

    public function whatsappConfiguration(): HasOne
    {
        return $this->hasOne(WhatsappConfiguration::class, 'primary_whatsapp_phone_number_id', 'id');
    }

    public function channel(): HasOneThrough
    {

        return $this->hasOneThrough(
            Channel::class,
            WhatsappConfiguration::class,
            'primary_whatsapp_phone_number_id', // Foreign key on WhatsappConfiguration
            'connector_id',                    // Foreign key on Channel
            'whatsapp_phone_number_id',        // Local key on WhatsappMessage
            'connector_id'                     // Local key on WhatsappConfiguration
        );

    }



}
