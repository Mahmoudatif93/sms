<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\MetaPageAccessToken
 *
 * @property int $id
 * @property string $meta_page_id
 * @property string $access_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read MetaPage $metaPage
 *
 * @method static Builder|MetaPageAccessToken newModelQuery()
 * @method static Builder|MetaPageAccessToken newQuery()
 * @method static Builder|MetaPageAccessToken query()
 * @method static Builder|MetaPageAccessToken whereId($value)
 * @method static Builder|MetaPageAccessToken whereMetaPageId($value)
 * @method static Builder|MetaPageAccessToken whereAccessToken($value)
 * @method static Builder|MetaPageAccessToken whereCreatedAt($value)
 * @method static Builder|MetaPageAccessToken whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MetaPageAccessToken extends Model
{
    protected $fillable = [
        'meta_page_id',
        'access_token',
    ];

    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }
}
