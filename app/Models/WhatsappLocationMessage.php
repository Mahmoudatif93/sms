<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * Represents a WhatsApp location message.
 *
 * This model is used to store information specifically for location messages sent or received
 * through WhatsApp. It includes details such as longitude, latitude, name, and address.
 *
 * @property int $id The primary key ID for the location message.
 * @property string $whatsapp_message_id The ID of the related message in the `whatsapp_messages` table.
 * @property string $longitude The longitude of the location.
 * @property string $latitude The latitude of the location.
 * @property string|null $name The name of the location (optional).
 * @property string|null $address The address of the location (optional).
 * @property Carbon|null $created_at The creation timestamp.
 * @property Carbon|null $updated_at The update timestamp.
 * @property-read WhatsappMessage $whatsappMessage The related `WhatsappMessage` model.
 *
 * @method static Builder|WhatsappLocationMessage newModelQuery()
 * @method static Builder|WhatsappLocationMessage newQuery()
 * @method static Builder|WhatsappLocationMessage query()
 * @method static Builder|WhatsappLocationMessage whereId($value)
 * @method static Builder|WhatsappLocationMessage whereWhatsappMessageId($value)
 * @method static Builder|WhatsappLocationMessage whereLongitude($value)
 * @method static Builder|WhatsappLocationMessage whereLatitude($value)
 * @method static Builder|WhatsappLocationMessage whereName($value)
 * @method static Builder|WhatsappLocationMessage whereAddress($value)
 * @method static Builder|WhatsappLocationMessage whereCreatedAt($value)
 * @method static Builder|WhatsappLocationMessage whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappLocationMessage extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_location_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'longitude',
        'latitude',
        'name',
        'address',
        'whatsapp_message_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the parent `WhatsappMessage` model.
     *
     * @return MorphOne
     */
    public function message(): MorphOne
    {
        return $this->morphOne(WhatsappMessage::class, 'messageable');
    }
}
