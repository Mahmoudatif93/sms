<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WhatsappFlow
 *
 * @property int $id The Flow ID from Meta
 * @property string $channel_id Foreign key to Channel
 * @property string $name Flow name
 * @property string|null $status Flow status (e.g., DRAFT, ACTIVE)
 * @property array|null $categories JSON array of flow categories
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Channel $channel
 *
 * @method static Builder|WhatsappFlow newModelQuery()
 * @method static Builder|WhatsappFlow newQuery()
 * @method static Builder|WhatsappFlow query()
 * @method static Builder|WhatsappFlow whereId($value)
 * @method static Builder|WhatsappFlow whereChannelId($value)
 * @method static Builder|WhatsappFlow whereName($value)
 * @method static Builder|WhatsappFlow whereStatus($value)
 * @method static Builder|WhatsappFlow whereCategories($value)
 * @method static Builder|WhatsappFlow whereCreatedAt($value)
 * @method static Builder|WhatsappFlow whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class WhatsappFlow extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'id';
    /**
     * The type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'unsignedBigInteger';

    protected $fillable = [
        'id',
        'channel_id',
        'name',
        'status',
        'categories',
    ];

    protected $casts = [
        'categories' => 'array',
    ];

    /**
     * Get the channel that owns this flow.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
