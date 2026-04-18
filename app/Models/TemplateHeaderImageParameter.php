<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OSS\OssClient;

/**
 * Class TemplateHeaderImageParameter
 *
 * Stores the image media handle for a WhatsApp template header.
 *
 * @package App\Models
 *
 * @property int $id Primary key.
 * @property int $template_message_header_component_id Foreign key to the header component.
 * @property string $link Media handle string for the image.
 * @property Carbon|null $created_at Timestamp when created.
 * @property Carbon|null $updated_at Timestamp when updated.
 *
 * @property-read TemplateMessageHeaderComponent|null $headerComponent The related header component.
 *
 * @method static Builder|TemplateHeaderImageParameter newModelQuery()
 * @method static Builder|TemplateHeaderImageParameter newQuery()
 * @method static Builder|TemplateHeaderImageParameter query()
 *
 * @mixin Eloquent
 */
class TemplateHeaderImageParameter extends Model
{
    protected $table = 'template_header_image_parameters';

    protected $fillable = [
        'tmpl_msg_hdr_component_id',
        'link',
    ];

    /**
     * Get the header component this parameter belongs to.
     */
    public function headerComponent(): BelongsTo
    {
        return $this->belongsTo(TemplateMessageHeaderComponent::class, 'tmpl_msg_hdr_component_id');
    }

    public function getMediaLinkAttribute(): ?string
    {
        return $this->regenerateSignedPreviewUrlFromLink($this->link);
    }

    public function regenerateSignedPreviewUrlFromLink(string $originalUrl, int $expirationInSeconds = 864000): ?string
    {
        try {
            // Parse the path from the full signed URL
            $parsedUrl = parse_url($originalUrl);

            if (!isset($parsedUrl['path'])) {
                return null;
            }
            // FIX: Decode path to avoid double-encoding
            $objectPath = urldecode(ltrim($parsedUrl['path'], '/'));

            // Initialize OSS client
            $ossClient = new OssClient(
                env('OSS_ACCESS_KEY_ID'),
                env('OSS_ACCESS_KEY_SECRET'),
                env('OSS_ENDPOINT')
            );

            $bucket = env('OSS_BUCKET');

            // Generate the signed previewable URL
            return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
                OssClient::OSS_HEADERS => [
                    'Content-Disposition' => 'inline',
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

}
