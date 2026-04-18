<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Eloquent;

/**
 * App\Models\TicketConfiguration
 *
 * @property int $id
 * @property string $connector_id
 * @property string $ticket_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connector $connector
 * @property-read TicketForm $ticketForm
 * @method static Builder|TicketConfiguration newModelQuery()
 * @method static Builder|TicketConfiguration newQuery()
 * @method static Builder|TicketConfiguration query()
 * @method static Builder|TicketConfiguration whereId($value)
 * @method static Builder|TicketConfiguration whereConnectorId($value)
 * @method static Builder|TicketConfiguration whereTicketId($value)
 * @method static Builder|TicketConfiguration whereStatus($value)
 * @method static Builder|TicketConfiguration whereCreatedAt($value)
 * @method static Builder|TicketConfiguration whereUpdatedAt($value)
 * @mixin Eloquent
 */
class TicketConfiguration extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_configuration';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'connector_id',
        'ticket_form_id',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the connector that owns the live chat configuration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    /**
     * Get the widget that owns the live chat configuration.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function ticketForm(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class);
    }
}