<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\MessengerConsumer
 * 
 * Represents a Messenger user identified by a Page-Scoped ID (PSID),
 * associated with a specific Facebook Page (meta_page_id).
 *
 * @property int $id Primary key ID.
 * @property string $meta_page_id The ID of the Meta Page this user belongs to.
 * @property string $psid The Messenger Page-Scoped ID.
 * @property string|null $name Display name of the user.
 * @property string|null $avatar_url Profile picture URL of the user (optional).
 * @property bool $is_active Whether the user is active or archived.
 * @property int $created_at
 * @property int $updated_at
 * @property-read MetaPage $metaPage
 * @method static Builder|MessengerConsumer newModelQuery()
 * @method static Builder|MessengerConsumer newQuery()
 * @method static Builder|MessengerConsumer query()
 * @method static Builder|MessengerConsumer whereId($value)
 * @method static Builder|MessengerConsumer whereMetaPageId($value)
 * @method static Builder|MessengerConsumer wherePsid($value)
 * @method static Builder|MessengerConsumer whereName($value)
 * @method static Builder|MessengerConsumer whereAvatarUrl($value)
 * @method static Builder|MessengerConsumer whereIsActive($value)
 * @method static Builder|MessengerConsumer whereCreatedAt($value)
 * @method static Builder|MessengerConsumer whereUpdatedAt($value)
 * @property string|null $contact_id
 * @property-read \App\Models\ContactEntity|null $contact
 * @method static Builder<static>|MessengerConsumer whereContactId($value)
 * @mixin Eloquent
 */
class MessengerConsumer extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'messenger_consumers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'meta_page_id',
        'psid',
        'name',
        'avatar_url',
        'is_active',
        'contact_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Get the Meta Page that owns this Messenger consumer.
     *
     * @return BelongsTo
     */
    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }
}
