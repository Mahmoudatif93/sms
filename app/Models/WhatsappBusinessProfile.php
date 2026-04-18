<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id The Whatsapp business Profile ID.
 * @property int $whatsapp_business_account_id The Whatsapp business account ID connected to it.
 * @property int $whatsapp_phone_number_id The Whatsapp Phone Number ID connected to it.
 * @property string|null $about The business's About text. This text appears in the business's profile, beneath its profile image, phone number, and contact buttons.
 * @property string|null $description Description of the business. Character limit 512.
 * @property string|null $profile_picture_url URL of the profile picture that was uploaded to Meta.
 * @property string|null $address Address of the business.
 * @property string|null $email Contact email address of the business.
 * @property string|null $vertical The vertical industry that this business associates with.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappBusinessAccount $whatsappBusinessAccount
 * @method static Builder|WhatsappBusinessProfile newModelQuery()
 * @method static Builder|WhatsappBusinessProfile newQuery()
 * @method static Builder|WhatsappBusinessProfile query()
 * @method static Builder|WhatsappBusinessProfile whereAbout($value)
 * @method static Builder|WhatsappBusinessProfile whereAddress($value)
 * @method static Builder|WhatsappBusinessProfile whereCreatedAt($value)
 * @method static Builder|WhatsappBusinessProfile whereDescription($value)
 * @method static Builder|WhatsappBusinessProfile whereEmail($value)
 * @method static Builder|WhatsappBusinessProfile whereId($value)
 * @method static Builder|WhatsappBusinessProfile whereProfilePictureUrl($value)
 * @method static Builder|WhatsappBusinessProfile whereUpdatedAt($value)
 * @method static Builder|WhatsappBusinessProfile whereVertical($value)
 * @method static Builder|WhatsappBusinessProfile whereWhatsappBusinessAccountId($value)
 * @method static Builder|WhatsappBusinessProfile whereWhatsappPhoneNumberId($value)
 * @mixin Eloquent
 */
class WhatsappBusinessProfile extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_business_profiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'whatsapp_business_account_id',
        'whatsapp_phone_number_id',
        'about',
        'description',
        'profile_picture_url',
        'address',
        'email',
        'vertical',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the WhatsApp Business Account that owns this profile.
     *
     * @return BelongsTo
     */
    public function whatsappBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }



}
