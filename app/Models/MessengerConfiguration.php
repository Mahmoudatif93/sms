<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\MessengerConfiguration
 *
 * @property string $id
 * @property string $connector_id
 * @property int $business_manager_account_id
 * @property string $meta_page_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Connector $connector
 * @property-read BusinessManagerAccount $businessManager
 * @property-read MetaPage $metaPage
 *
 * @method static Builder|MessengerConfiguration newModelQuery()
 * @method static Builder|MessengerConfiguration newQuery()
 * @method static Builder|MessengerConfiguration query()
 * @method static Builder|MessengerConfiguration whereConnectorId($value)
 * @method static Builder|MessengerConfiguration whereBusinessManagerAccountId($value)
 * @method static Builder|MessengerConfiguration whereMetaPageId($value)
 * @method static Builder|MessengerConfiguration whereStatus($value)
 * @method static Builder|MessengerConfiguration whereCreatedAt($value)
 * @method static Builder|MessengerConfiguration whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MessengerConfiguration extends Model
{
    use HasUuids;

    protected $table = 'messenger_configurations';

    protected $fillable = [
        'id',
        'connector_id',
        'business_manager_account_id',
        'meta_page_id',
        'status',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function businessManager(): BelongsTo
    {
        return $this->belongsTo(BusinessManagerAccount::class, 'business_manager_account_id');
    }

    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }
}
