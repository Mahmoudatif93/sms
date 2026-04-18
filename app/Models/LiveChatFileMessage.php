<?php

namespace App\Models;

use App\Services\FileUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use OSS\OssClient;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * App\Models\LiveChatFileMessage
 *
 * @property string $id
 * @property string $media_id
 * @property string $link
 * @property string $caption
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read LiveChatMessage $message
 */
class LiveChatFileMessage extends Model implements HasMedia
{
    use SoftDeletes,InteractsWithMedia,HasUuids;

    protected $table = 'livechat_file_messages';

    protected $fillable = [
        'media_id',
        'link',
        'caption',
    ];

    /**
     * Get the message that owns this file message.
     */
    public function message(): MorphOne
    {
        return $this->morphOne(LiveChatMessage::class, 'messageable');
    }

     /**
     * Get a signed URL for previewing the media with 'Content-Disposition: inline'
     *
     * @param int $expirationInSeconds
     * @return string|null
     */
    public function getMediaUrl(int $expirationInSeconds = 864000): ?string
    {
        $fileUploadService = app(FileUploadService::class);
        return $fileUploadService->getSignUrl($this->getMedia('*')[0]->getPath());
    }

    public function getSignedMediaUrlForPreview(int $expirationInSeconds = 864000): ?string
    {
        // Retrieve the media using Spatie's getFirstMedia method
        $media = $this->getFirstMedia('livechat_media');  

        if (!$media) {
            return null;  // Return null if no media found
        }

        // Get the path of the media object in OSS
        $objectPath = $media->getPath(); // This retrieves the path in OSS

        // Initialize OSS Client
        $ossClient = new OssClient(
            env('OSS_ACCESS_KEY_ID'),
            env('OSS_ACCESS_KEY_SECRET'),
            env('OSS_ENDPOINT')
        );

        $bucket = env('OSS_BUCKET');

        // Generate the signed URL with 'Content-Disposition: inline' for preview
        return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
            OssClient::OSS_HEADERS => [
                'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
            ],
        ]);
    }
}