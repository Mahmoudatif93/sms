<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * App\Models\MetaWebhookLog
 *
 * @property int $id
 * @property array|string|null $payload
 * @property bool $processed
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder|MetaWebhookLog newModelQuery()
 * @method static Builder|MetaWebhookLog newQuery()
 * @method static Builder|MetaWebhookLog query()
 * @method static Builder|MetaWebhookLog whereProcessed(bool $processed)
 *
 * @mixin Eloquent
 */
class MetaWebhookLog extends Model
{

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payload',
        'processed',
        'processed_at',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'json',
        'processed' => 'boolean',
        'processed_at' => 'timestamp',
    ];
}
