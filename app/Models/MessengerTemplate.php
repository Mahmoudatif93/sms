<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OSS\OssClient;

/**
 * MessengerTemplate Model
 *
 * Represents a reusable message template for Facebook Messenger.
 *
 * @property string $id
 * @property string $meta_page_id
 * @property string $name
 * @property string $type
 * @property array $payload
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read MetaPage $metaPage
 */
class MessengerTemplate extends Model implements HasMedia
{
    use HasUuids, InteractsWithMedia;

    const TYPE_GENERIC = 'generic';
    const TYPE_BUTTON = 'button';
    const TYPE_MEDIA = 'media';
    const TYPE_RECEIPT = 'receipt';
    const TYPE_COUPON = 'coupon';

    const TYPES = [
        self::TYPE_GENERIC,
        self::TYPE_BUTTON,
        self::TYPE_MEDIA,
        self::TYPE_RECEIPT,
        self::TYPE_COUPON,
    ];

    protected $table = 'messenger_templates';

    protected $fillable = [
        'meta_page_id',
        'name',
        'type',
        'payload',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['media'];

    /**
     * Get the Meta Page that owns this template.
     */
    public function metaPage(): BelongsTo
    {
        return $this->belongsTo(MetaPage::class, 'meta_page_id');
    }

    /**
     * Scope to filter active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Build the message attachment payload for Facebook API.
     */
    public function toMessengerPayload(): array
    {
        $payload = $this->getPayloadWithMediaUrls();

        return [
            'attachment' => [
                'type' => 'template',
                'payload' => array_merge(
                    ['template_type' => $this->type],
                    $payload
                ),
            ],
        ];
    }

    /**
     * Get payload with resolved media URLs from Spatie collections.
     */
    public function getPayloadWithMediaUrls(): array
    {
        $payload = $this->getRawPayload();

        // Process elements for generic/media templates
        if (isset($payload['elements'])) {
            foreach ($payload['elements'] as $index => $element) {
                // Handle image_url for generic template
                if (isset($element['image_url']) && str_starts_with($element['image_url'], 'media:')) {
                    $mediaIndex = (int) str_replace('media:', '', $element['image_url']);
                    $mediaUrl = $this->getMediaUrl("element_image_{$mediaIndex}");
                    if ($mediaUrl) {
                        $payload['elements'][$index]['image_url'] = $mediaUrl;
                    }
                }

                // Handle attachment_id/url for media template
                if (isset($element['url']) && str_starts_with($element['url'], 'media:')) {
                    $mediaIndex = (int) str_replace('media:', '', $element['url']);
                    $mediaUrl = $this->getMediaUrl("media_element_{$mediaIndex}");
                    if ($mediaUrl) {
                        $payload['elements'][$index]['url'] = $mediaUrl;
                    }
                }
            }
        }

        // Handle image_url for coupon template
        if (isset($payload['image_url']) && str_starts_with($payload['image_url'], 'media:')) {
            $mediaUrl = $this->getMediaUrl('coupon_image');
            if ($mediaUrl) {
                $payload['image_url'] = $mediaUrl;
            }
        }

        return $payload;
    }

    /**
     * Get signed URL for a media collection.
     */
    public function getMediaUrl(string $collection): ?string
    {
        $media = $this->getFirstMedia($collection);

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

    /**
     * Get the raw payload without resolved URLs (for internal use).
     */
    public function getRawPayload(): array
    {
        return $this->attributes['payload']
            ? json_decode($this->attributes['payload'], true)
            : [];
    }

    /**
     * Override toArray to return resolved payload with media URLs.
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Replace payload with resolved URLs
        $array['payload'] = $this->getPayloadWithMediaUrls();

        return $array;
    }
}
