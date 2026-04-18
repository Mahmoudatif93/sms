<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class WhatsappMessageStatusError
 *
 * Represents the errors that occur during the WhatsApp message status update process.
 * Each error is related to a specific WhatsApp message status.
 *
 * @property int $id The primary key of the error record.
 * @property int $whatsapp_message_status_id The ID of the associated WhatsApp message status.
 * @property int|null $error_code The error code returned by the WhatsApp API.
 * @property string|null $error_title The title or name of the error.
 * @property string|null $error_message The error message returned by the WhatsApp API.
 * @property string|null $error_details Additional details describing the error.
 * @property Carbon|null $created_at Timestamp when the error record was created.
 * @property Carbon|null $updated_at Timestamp when the error record was last updated.
 *
 * @property-read WhatsappMessageStatus $whatsappMessageStatus The related WhatsApp message status.
 *
 * @method static Builder|WhatsappMessageStatusError newModelQuery()
 * @method static Builder|WhatsappMessageStatusError newQuery()
 * @method static Builder|WhatsappMessageStatusError query()
 * @method static Builder|WhatsappMessageStatusError whereId($value)
 * @method static Builder|WhatsappMessageStatusError whereWhatsappMessageStatusId($value)
 * @method static Builder|WhatsappMessageStatusError whereErrorCode($value)
 * @method static Builder|WhatsappMessageStatusError whereErrorTitle($value)
 * @method static Builder|WhatsappMessageStatusError whereErrorMessage($value)
 * @method static Builder|WhatsappMessageStatusError whereErrorDetails($value)
 * @method static Builder|WhatsappMessageStatusError whereCreatedAt($value)
 * @method static Builder|WhatsappMessageStatusError whereUpdatedAt($value)
 * @mixin Eloquent
 */
class WhatsappMessageStatusError extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_message_status_errors';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'whatsapp_message_status_id',
        'error_code',
        'error_title',
        'error_message',
        'error_details',
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
     * Get the WhatsApp message status associated with this error.
     *
     * @return BelongsTo
     */
    public function whatsappMessageStatus(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageStatus::class, 'whatsapp_message_status_id', 'id');
    }
}
