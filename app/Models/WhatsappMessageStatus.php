<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents the status of a WhatsApp message.
 *
 * This model stores information about the status updates for WhatsApp messages.
 * Each status update is linked to a specific WhatsApp message and includes details
 * such as the status, timestamp, and additional information about the status.
 *
 * @property string $id The unique identifier for each status update.
 * @property string $whatsapp_message_id The ID of the WhatsApp message this status is related to.
 * @property string $status The status of the message (e.g., delivered, read, failed).
 * @property int $timestamp The time when this status was recorded.
 * @property Carbon|null $created_at The timestamp when the record was created.
 * @property Carbon|null $updated_at The timestamp when the record was last updated.
 * @property-read WhatsappMessage $whatsappMessage The WhatsApp message associated with this status.
 * @method static Builder|WhatsappMessageStatus newModelQuery()
 * @method static Builder|WhatsappMessageStatus newQuery()
 * @method static Builder|WhatsappMessageStatus query()
 * @method static Builder|WhatsappMessageStatus whereCreatedAt($value)
 * @method static Builder|WhatsappMessageStatus whereId($value)
 * @method static Builder|WhatsappMessageStatus whereTimestamp($value)
 * @method static Builder|WhatsappMessageStatus whereUpdatedAt($value)
 * @method static Builder|WhatsappMessageStatus whereWhatsappMessageId($value)
 * @method static Builder|WhatsappMessageStatus whereStatus($value)
 * @mixin Eloquent
 */
class WhatsappMessageStatus extends Model
{

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_message_statuses';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'whatsapp_message_id',
        'status',
        'timestamp',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'integer',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the WhatsApp message associated with this status.
     *
     * @return BelongsTo
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id', 'id');
    }

    /**
     * Get the errors associated with this status.
     *
     * @return HasMany
     */
    public function errors(): HasMany
    {
        return $this->hasMany(WhatsappMessageStatusError::class);
    }
}
