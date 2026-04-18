<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OSS\OssClient;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Constants\Meta;
use Illuminate\Support\Facades\Http;
use App\Traits\WhatsappMediaManager;
use App\Services\FileUploadService;
/**
 *
 *
 * @property int $id
 * @property string $whatsapp_message_id Foreign key to whatsapp_messages
 * @property string|null $media_id
 * @property string|null $link
 * @property string|null $caption
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 * @method static Builder|WhatsappImageMessage newModelQuery()
 * @method static Builder|WhatsappImageMessage newQuery()
 * @method static Builder|WhatsappImageMessage query()
 * @method static Builder|WhatsappImageMessage whereCaption($value)
 * @method static Builder|WhatsappImageMessage whereCreatedAt($value)
 * @method static Builder|WhatsappImageMessage whereId($value)
 * @method static Builder|WhatsappImageMessage whereLink($value)
 * @method static Builder|WhatsappImageMessage whereMediaId($value)
 * @method static Builder|WhatsappImageMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappImageMessage whereWhatsappMessageId($value)
 * @mixin Eloquent
 */
class WhatsappImageMessage extends Model implements HasMedia
{
    use InteractsWithMedia, WhatsappMediaManager;

    protected $fillable = [
        'whatsapp_message_id',
        'media_id',
        'link',
        'caption',
    ];

    // protected $appends = ['media_link'];

    // public function getMediaLinkAttribute(): ?string
    // {
    //     return $this->getSignedMediaUrlForPreview();
    // }


    /**
     * Relationship: Each image message belongs to a WhatsApp message.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id', 'id');
    }

    public function getLinkAttribute($value)
    {
        $media = $this->getFirstMedia('*');  // Replace 'whatsapp-images' with your media collection name

        if (!$media) {
            return null;  // Return null if no media found
        }
        return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
        // $fileUploadService = app(FileUploadService::class);
        // $media = $this->getMedia("*");
        // if ($media && count($media) > 0) {
        //     return $fileUploadService->getSignUrl($media[0]->getPath());
        // }
        // return $value;
    }

    /**
     * Get a signed URL for previewing the media with 'Content-Disposition: inline'
     *
     * @param int $expirationInSeconds
     * @return string|null
     */
    // public function getMediaUrl(int $expirationInSeconds = 864000): ?string
    // {
    //     $fileUploadService = app(FileUploadService::class);
    //     if ($this->media_id) {
    //         return $fileUploadService->getSignUrl($this->getMedia('*')[0]->getPath());
    //     } elseif ($this->link) {
    //         return $this->getMediaUrlFromOssForPreview($expirationInSeconds);
    //     }

    //     return null;

    // }
    // public function getSignedMediaUrlForPreview(int $expirationInSeconds = 864000): ?string
    // {
    //     // Retrieve the media using Spatie's getFirstMedia method
    // $media = $this->getFirstMedia('whatsapp-images');  // Replace 'whatsapp-images' with your media collection name

    // if (!$media) {
    //     return null;  // Return null if no media found
    // }
    // return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
    //     //     // Get the path of the media object in OSS
    //     //     $objectPath = $media->getPath(); // This retrieves the path in OSS

    //     //     // Initialize OSS Client
    //     //     $ossClient = new OssClient(
    //     //         env('OSS_ACCESS_KEY_ID'),
    //     //         env('OSS_ACCESS_KEY_SECRET'),
    //     //         env('OSS_ENDPOINT')
    //     //     );

    //     //     $bucket = env('OSS_BUCKET');

    //     //     // Generate the signed URL with 'Content-Disposition: inline' for preview
    //     //     return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
    //     //         OssClient::OSS_HEADERS => [
    //     //             'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
    //     //         ],
    //     //     ]);
    // }

}
