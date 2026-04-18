<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;




/**
 * Represents a consumer's phone number associated with a WhatsApp Business Account.
 *
 *  This model functions as a contact list for the WhatsApp Business Account, storing information
 *  about phone numbers that have interacted with the business. Each entry represents a consumer
 *  phone number that the business has previously communicated with or plans to communicate with.
 *
 * @property int $id Primary key ID.
 * @property int $whatsapp_business_account_id The WhatsApp business account ID.
 * @property string|null $wa_id The customer's WhatsApp Phone Number ID.
 * @property string $phone_number The actual phone number of the customer.
 * @property string|null $name The name associated with the customer's phone number.
 * @property bool $is_active Whether the customer's phone number is active or not.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsAppBusinessAccount $whatsappBusinessAccount
 * @method static Builder|WhatsappConsumerPhoneNumber newModelQuery()
 * @method static Builder|WhatsappConsumerPhoneNumber newQuery()
 * @method static Builder|WhatsappConsumerPhoneNumber query()
 * @method static Builder|WhatsappConsumerPhoneNumber whereCreatedAt($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereId($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereIsActive($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereName($value)
 * @method static Builder|WhatsappConsumerPhoneNumber wherePhoneNumber($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereUpdatedAt($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereWaId($value)
 * @method static Builder|WhatsappConsumerPhoneNumber whereWhatsappBusinessAccountId($value)
 * @mixin Eloquent
 */
class WhatsappConsumerPhoneNumber extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_consumer_phone_numbers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_business_account_id',
        'wa_id',
        'phone_number',
        'name',
        'is_active',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the WhatsApp Business Account associated with the consumer phone number.
     *
     * @return BelongsTo
     */
    public function whatsappBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }
}
