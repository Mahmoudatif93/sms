<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class ChannelFlowKey
 *
 * Stores the RSA key pair used for WhatsApp Flows per channel.
 *
 * @property string $id
 * @property string $channel_id
 * @property string $public_key
 * @property string $private_key (encrypted at rest)
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Channel $channel
 *
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey whereChannelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey wherePublicKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChannelFlowKey whereUpdatedAt($value)
 */
class ChannelFlowKey extends Model
{

    protected $table = 'channel_flow_keys';

    protected $fillable = [
        'id',
        'channel_id',
        'public_key',
        'private_key',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Automatically encrypt the private_key before storing.
     */
    public function setPrivateKeyAttribute($value): void
    {
        $this->attributes['private_key'] = encrypt($value);
    }

    /**
     * Automatically decrypt the private_key when accessing.
     */
    public function getPrivateKeyAttribute($value): string
    {
        return decrypt($value);
    }

    /**
     * Get the channel this key is associated with.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
