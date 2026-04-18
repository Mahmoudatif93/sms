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
 * @method static Builder|WhatsappVideoMessage newModelQuery()
 * @method static Builder|WhatsappVideoMessage newQuery()
 * @method static Builder|WhatsappVideoMessage query()
 * @method static Builder|WhatsappVideoMessage whereCaption($value)
 * @method static Builder|WhatsappVideoMessage whereCreatedAt($value)
 * @method static Builder|WhatsappVideoMessage whereId($value)
 * @method static Builder|WhatsappVideoMessage whereLink($value)
 * @method static Builder|WhatsappVideoMessage whereMediaId($value)
 * @method static Builder|WhatsappVideoMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappVideoMessage whereWhatsappMessageId($value)
 * @mixin Eloquent
 */
class WhatsappVideoMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_video_messages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
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
     * Get the associated WhatsApp message.
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id', 'id');
    }

    /**
     * Get a signed URL for previewing the media with 'Content-Disposition: inline'
     *
     * @param int $expirationInSeconds
     * @return string|null
     */

    public function getLinkAttribute($value)
    {
        $media = $this->getFirstMedia('*');  // Replace 'whatsapp-images' with your media collection name

        if (!$media) {
            return null;  // Return null if no media found
        }
        return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
        // $fileUploadService = app(FileUploadService::class);
        // $media = $this->getMedia("*");
        // if($media && count($media) >0){
        //     return $fileUploadService->getSignUrl($media[0]->getPath());
        // }
        //     return $value;
    }

    // public function getSignedMediaUrlForPreview(int $expirationInSeconds = 864000): ?string
    // {
    //     // Retrieve the media using Spatie's getFirstMedia method
    //     $media = $this->getFirstMedia('whatsapp-images');  // Replace 'whatsapp-images' with your media collection name

    //     if (!$media) {
    //         return null;  // Return null if no media found
    //     }

    //     // Get the path of the media object in OSS
    //     $objectPath = $media->getPath(); // This retrieves the path in OSS

    //     // Initialize OSS Client
    //     $ossClient = new OssClient(
    //         env('OSS_ACCESS_KEY_ID'),
    //         env('OSS_ACCESS_KEY_SECRET'),
    //         env('OSS_ENDPOINT')
    //     );

    //     $bucket = env('OSS_BUCKET');

    //     // Generate the signed URL with 'Content-Disposition: inline' for preview
    //     return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
    //         OssClient::OSS_HEADERS => [
    //             'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
    //         ],
    //     ]);
    // }
}
