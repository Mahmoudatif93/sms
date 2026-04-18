<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OSS\Core\OssException;
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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 * @method static Builder|WhatsappAudioMessage newModelQuery()
 * @method static Builder|WhatsappAudioMessage newQuery()
 * @method static Builder|WhatsappAudioMessage query()
 * @method static Builder|WhatsappAudioMessage whereCreatedAt($value)
 * @method static Builder|WhatsappAudioMessage whereId($value)
 * @method static Builder|WhatsappAudioMessage whereLink($value)
 * @method static Builder|WhatsappAudioMessage whereMediaId($value)
 * @method static Builder|WhatsappAudioMessage whereUpdatedAt($value)
 * @method static Builder|WhatsappAudioMessage whereWhatsappMessageId($value)
 * @mixin Eloquent
 */
class WhatsappAudioMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    // Table associated with the model
    protected $table = 'whatsapp_audio_messages';

    // Fillable fields
    protected $fillable = [
        'whatsapp_message_id',
        'media_id',
        'link'
    ];

    // protected $appends = ['media_link'];

    // public function getMediaLinkAttribute(): ?string
    // {
    //     return $this->getSignedMediaUrlForPreview();
    // }


    // Define the relationship with the WhatsappMessage model
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * Get a signed URL for previewing the media with 'Content-Disposition: inline'
     *
     * @param int $expirationInSeconds
     * @return string|null
     */
    // public function getSignedMediaUrlForPreview(int $expirationInSeconds = 864000): ?string
    // {
    //     // Retrieve the media using Spatie's getFirstMedia method
    //     $media = $this->getFirstMedia('whatsapp-audios');  // Replace 'whatsapp-images' with your media collection name

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

    public function getLinkAttribute($value)
    {
         $media = $this->getFirstMedia('*');  // Replace 'whatsapp-images' with your media collection name

        if (!$media) {
            return null;  // Return null if no media found
        }
        return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
    }
}
