<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Eloquent;

/**
 * App\Models\LiveChatConfiguration
 *
 * @property int $id
 * @property string $connector_id
 * @property string $widget_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connector $connector
 * @property-read Widget $widget
 * @method static Builder|LiveChatConfiguration newModelQuery()
 * @method static Builder|LiveChatConfiguration newQuery()
 * @method static Builder|LiveChatConfiguration query()
 * @method static Builder|LiveChatConfiguration whereId($value)
 * @method static Builder|LiveChatConfiguration whereConnectorId($value)
 * @method static Builder|LiveChatConfiguration whereWidgetId($value)
 * @method static Builder|LiveChatConfiguration whereStatus($value)
 * @method static Builder|LiveChatConfiguration whereCreatedAt($value)
 * @method static Builder|LiveChatConfiguration whereUpdatedAt($value)
 * @mixin Eloquent
 */
class LiveChatConfiguration extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'livechat_configurations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'connector_id',
        'widget_id',
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
    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }
}
