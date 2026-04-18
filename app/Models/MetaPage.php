<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * App\Models\MetaPage
 *
 * @property string $id Facebook Page ID
 * @property string $name
 * @property int $business_manager_account_id
 * @property string|null $about
 * @property string|null $bio
 * @property string|null $description
 * @property string|null $link
 * @property string|null $verification_status
 * @property string|null $website
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read BusinessManagerAccount $businessManager
 * @method static Builder|MetaPage newModelQuery()
 * @method static Builder|MetaPage newQuery()
 * @method static Builder|MetaPage query()
 * @method static Builder|MetaPage whereId($value)
 * @method static Builder|MetaPage whereName($value)
 * @method static Builder|MetaPage whereAbout($value)
 * @method static Builder|MetaPage whereBio($value)
 * @method static Builder|MetaPage whereDescription($value)
 * @method static Builder|MetaPage whereLink($value)
 * @method static Builder|MetaPage whereVerificationStatus($value)
 * @method static Builder|MetaPage whereWebsite($value)
 * @method static Builder|MetaPage whereCreatedAt($value)
 * @method static Builder|MetaPage whereUpdatedAt($value)
 * @method static Builder|MetaPage whereBusinessManagerAccountId($value)
 * @property-read Collection<int, MetaPageAccessToken> $accessTokens
 * @property-read int|null $access_tokens_count
 * @property-read Connector|null $connector
 * @property-read string|null $workspace_id
 * @property-read MessengerConfiguration|null $messengerConfiguration
 * @property-read \App\Models\Channel|null $channel
 * @property-read string|null $channel_id
 * @property-read \App\Models\Workspace|null $workspace
 * @mixin Eloquent
 */
class MetaPage extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'business_manager_account_id',
        'about',
        'bio',
        'description',
        'link',
        'verification_status',
        'website',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function businessManager(): BelongsTo
    {
        return $this->belongsTo(BusinessManagerAccount::class, 'business_manager_account_id');
    }

    public function accessTokens(): HasMany
    {
        return $this->hasMany(MetaPageAccessToken::class, 'meta_page_id');
    }

    public function messengerConfiguration(): HasOne|Builder|MetaPage
    {
        return $this->hasOne(MessengerConfiguration::class, 'meta_page_id');
    }

    public function connector(): HasOneThrough
    {
        return $this->hasOneThrough(
            Connector::class,
            MessengerConfiguration::class,
            'meta_page_id',     // Foreign key on MessengerConfiguration (to MetaPage)
            'id',               // Foreign key on Connector
            'id',               // Local key on MetaPage
            'connector_id'      // Local key on MessengerConfiguration (to Connector)
        );
    }

    public function workspace(): ?BelongsTo
    {
        return $this->connector?->workspace(); // uses the existing relation on Connector
    }

    public function getWorkspaceIdAttribute(): ?string
    {
        return $this->connector?->workspace_id;
    }

    public function channel(): HasOneThrough
    {
        return $this->hasOneThrough(
            Channel::class,
            MessengerConfiguration::class,
            'meta_page_id',     // Foreign key on MessengerConfiguration
            'connector_id',     // Foreign key on Channel
            'id',               // Local key on MetaPage
            'connector_id'      // Local key on MessengerConfiguration
        );
    }

    public function getChannelIdAttribute(): ?string
    {
        return $this->channel?->id;
    }


}
