<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *
 *
 * @property-read Channel|null $channel
 * @property-read Workspace|null $workspace
 * @method static Builder|Connector newModelQuery()
 * @method static Builder|Connector newQuery()
 * @method static Builder|Connector query()
 * @property int $id
 * @property string $workspace_id
 * @property string $name
 * @property string $status
 * @property string|null $region
 * @property int|null $created_at
 * @property int|null $updated_at
 * @method static Builder|Connector whereCreatedAt($value)
 * @method static Builder|Connector whereId($value)
 * @method static Builder|Connector whereName($value)
 * @method static Builder|Connector whereRegion($value)
 * @method static Builder|Connector whereStatus($value)
 * @method static Builder|Connector whereUpdatedAt($value)
 * @method static Builder|Connector whereWorkspaceId($value)
 * @property-read WhatsappConfiguration|null $whatsappConfiguration
 * @property-read SmsConfiguration|null $SmsConfiguration
 * @property-read LiveChatConfiguration|null $liveChatConfiguration
 * @property-read MessengerConfiguration|null $messengerConfiguration
 * @mixin Eloquent
 */
class Connector extends Model
{

    use HasUuids;

    protected $table = 'connectors'; // Disable auto-increment

    protected $fillable = [
        'id',
        'workspace_id',
        'name',
        'status',
        'region'
    ];
    protected $casts = ['created_at' => 'timestamp', 'updated_at' => 'timestamp'];


    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): HasOne
    {
        return $this->hasOne(Channel::class);
    }

    public function whatsappConfiguration(): HasOne
    {
        return $this->hasOne(WhatsappConfiguration::class, 'connector_id', 'id');

    }

    public function SmsConfiguration(): HasOne
    {
        return $this->hasOne(SmsConfiguration::class, 'connector_id', 'id');

    }

    public function liveChatConfiguration(): HasOne
    {
        return $this->hasOne(LiveChatConfiguration::class, 'connector_id', 'id');

    }

    public function ticketConfiguration(): HasOne
    {
        return $this->hasOne(TicketConfiguration::class, 'connector_id', 'id');
    }
    public function messengerConfiguration(): HasOne
    {
        return $this->hasOne(MessengerConfiguration::class, 'connector_id', 'id');

    }


}
