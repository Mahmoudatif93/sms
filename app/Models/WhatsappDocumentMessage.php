<?php

namespace App\Models;

use App\Services\FileUploadService;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OSS\Core\OssException;
use OSS\OssClient;
use App\Traits\WhatsappMediaManager;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property string $whatsapp_message_id
 * @property string|null $media_id
 * @property string|null $link
 * @property string|null $caption
 * @property string|null $filename
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WhatsappMessage $whatsappMessage
 */
class WhatsappDocumentMessage extends Model implements HasMedia
{
    use InteractsWithMedia, WhatsappMediaManager;

    protected $table = 'whatsapp_document_messages';

    protected $fillable = [
        'whatsapp_message_id',
        'media_id',
        'link',
        'caption',
        'filename',
    ];

    // protected $appends = ['media_link'];

    /**
     * Relation with WhatsappMessage
     */
    public function whatsappMessage(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessage::class, 'whatsapp_message_id');
    }

    /**
     * Signed URL accessor (Unified structure with audio / video / image)
     */
    // public function getMediaLinkAttribute(): ?string
    // {
    //     return $this->getSignedMediaUrlForPreview();
    // }

    /**
     * Get a signed URL for previewing the document
     */
    // public function getSignedMediaUrlForPreview(int $expirationInSeconds = 864000): ?string
    // {
    //     $media = $this->getFirstMedia('whatsapp-documents');

    //     if (!$media) {
    //         return null;
    //     }

    //     $objectPath = $media->getPath();

    //     try {
    //         $ossClient = new OssClient(
    //             env('OSS_ACCESS_KEY_ID'),
    //             env('OSS_ACCESS_KEY_SECRET'),
    //             env('OSS_ENDPOINT')
    //         );

    //         $bucket = env('OSS_BUCKET');

    //         return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
    //             OssClient::OSS_HEADERS => [
    //                 'Content-Disposition' => 'inline',
    //             ],
    //         ]);
    //     } catch (OssException $e) {
    //         return null;
    //     }
    // }

    /**
     * Use FileUploadService to return direct signed URL from OSS
     * if available
     */
    public function getLinkAttribute($value)
    {

        $media = $this->getFirstMedia('whatsapp-documents');  // Replace 'whatsapp-images' with your media collection name
        if (!$media) {
            return null;  // Return null if no media found
        }
         return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
        // return \Storage::disk('oss')->temporaryUrl(
        //     $media->getPath(),
        //     now()->addMinutes(10)
        // );

        // $fileUploadService = app(FileUploadService::class);

        // $media = $this->getMedia('*');
        // if ($media && count($media) > 0) {
        //     return $fileUploadService->getSignUrl($media[0]->getPath());
        // }

        // return $value;
    }
}
