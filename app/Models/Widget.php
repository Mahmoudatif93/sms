<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OSS\OssClient;

/**
 * App\Models\Widget
 *
 * @property string $id
 * @property string $organization_id
 * @property string|null $theme_color
 * @property string|null $logo_url
 * @property string|null $welcome_message
 * @property string|null $offline_message
 * @property string|null $message_placeholder
 * @property bool $is_active
 * @property bool $show_agent_avatar
 * @property bool $show_agent_name
 * @property bool $show_file_upload
 * @property string $position
 * @property int $z_index
 * @property string $language
 * @property bool $working_hours_enabled
 * @property array|null $working_hours
 * @property bool $require_name_email
 * @property bool $sound_enabled
 * @property bool $auto_open
 * @property int $auto_open_delay
 * @property bool $collect_visitor_data
 * @property array|null $allowed_domains
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read LiveChatConfiguration|null $liveChatConfiguration
 * @property-read PreChatForm[] $preChatForms
 * @property-read PostChatForm[] $postChatForms
 * @method static Builder|Widget newModelQuery()
 * @method static Builder|Widget newQuery()
 * @method static Builder|Widget query()
 * @method static Builder|Widget whereId($value)
 * @method static Builder|Widget whereOrganizationId($value)
 * @method static Builder|Widget whereThemeColor($value)
 * @method static Builder|Widget whereLogoUrl($value)
 * @method static Builder|Widget whereWelcomeMessage($value)
 * @method static Builder|Widget whereOfflineMessage($value)
 * @method static Builder|Widget whereIsActive($value)
 * @method static Builder|Widget whereShowAgentAvatar($value)
 * @method static Builder|Widget whereShowAgentName($value)
 * @method static Builder|Widget whereShowFileUpload($value)
 * @method static Builder|Widget wherePosition($value)
 * @method static Builder|Widget whereZIndex($value)
 * @method static Builder|Widget whereLanguage($value)
 * @method static Builder|Widget whereWorkingHoursEnabled($value)
 * @method static Builder|Widget whereWorkingHours($value)
 * @method static Builder|Widget whereRequireNameEmail($value)
 * @method static Builder|Widget whereSoundEnabled($value)
 * @method static Builder|Widget whereAutoOpen($value)
 * @method static Builder|Widget whereAutoOpenDelay($value)
 * @method static Builder|Widget whereCollectVisitorData($value)
 * @method static Builder|Widget whereAllowedDomains($value)
 * @method static Builder|Widget whereCreatedAt($value)
 * @method static Builder|Widget whereUpdatedAt($value)
 * @method static Builder|Widget whereDeletedAt($value)
 * @method static \Illuminate\Database\Query\Builder|Widget onlyTrashed()
 * @method static \Illuminate\Database\Query\Builder|Widget withTrashed()
 * @method static \Illuminate\Database\Query\Builder|Widget withoutTrashed()
 * @mixin Eloquent
 */

class Widget extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    protected $fillable = [
        'organization_id',
        'theme_color',
        'logo_url',
        'welcome_message',
        'offline_message',
        'message_placeholder',
        'is_active',
        'show_agent_avatar',
        'show_agent_name',
        'show_file_upload',
        'position',
        'z_index',
        'language',
        'working_hours_enabled',
        'working_hours',
        'require_name_email',
        'sound_enabled',
        'auto_open',
        'auto_open_delay',
        'collect_visitor_data',
        'allowed_domains'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid(); // Generate a UUID
        });
    }

    /**
     * A workspace belongs to an organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * A widget has one live chat configuration.
     */
    public function liveChatConfiguration(): BelongsTo
    {
        return $this->belongsTo(LiveChatConfiguration::class, 'widget_id');
    }

    /**
     * Get the pre-chat forms for this channel.
     */
    public function preChatForms(): HasMany
    {
        return $this->hasMany(PreChatForm::class);
    }

    /**
     * Get the post-chat forms for this channel.
     */
    public function postChatForms(): HasMany
    {
        return $this->hasMany(PostChatForm::class);
    }

    /**
     * Get the logo URL from media collection.
     */
    public function getLogoUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('logo');

        if (!$media) {
            return null;
        }

        $objectPath = $media->getPath();

        $ossClient = new OssClient(
            env('OSS_ACCESS_KEY_ID'),
            env('OSS_ACCESS_KEY_SECRET'),
            env('OSS_ENDPOINT')
        );

        return $ossClient->signUrl(env('OSS_BUCKET'), $objectPath, 864000, 'GET', [
            OssClient::OSS_HEADERS => [
                'Content-Disposition' => 'inline',
            ],
        ]);
    }
}
