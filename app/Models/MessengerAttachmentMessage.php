<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * App\Models\MessengerAttachmentMessage
 *
 * @property int $id
 * @property string $messenger_message_id
 * @property string $type (image, video, audio, file, document)
 * @property string|null $attachment_id Facebook attachment ID (for reusable attachments)
 * @property string|null $url
 * @property string|null $filename
 * @property string|null $caption
 * @property int|null $media_id Spatie media ID
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read MessengerMessage $messengerMessage
 *
 * @method static Builder|MessengerAttachmentMessage newModelQuery()
 * @method static Builder|MessengerAttachmentMessage newQuery()
 * @method static Builder|MessengerAttachmentMessage query()
 *
 * @mixin Eloquent
 */
class MessengerAttachmentMessage extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const TYPE_IMAGE = 'image';
    public const TYPE_VIDEO = 'video';
    public const TYPE_AUDIO = 'audio';
    public const TYPE_FILE = 'file';
    public const TYPE_DOCUMENT = 'document';

    protected $table = 'messenger_attachment_messages';

    protected $fillable = [
        'messenger_message_id',
        'type',
        'attachment_id',
        'url',
        'filename',
        'caption',
        'media_id',
    ];

    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    public function messengerMessage(): BelongsTo
    {
        return $this->belongsTo(MessengerMessage::class, 'messenger_message_id');
    }

    public function getUrlAttribute($value): ?string
    {
        $media = $this->getFirstMedia('*');

        if (!$media) {
            return $value;
        }

        return $media->getTemporaryUrl(Carbon::now()->addMinutes(10));
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('messenger-images');
        $this->addMediaCollection('messenger-videos');
        $this->addMediaCollection('messenger-audios');
        $this->addMediaCollection('messenger-files');
        $this->addMediaCollection('messenger-documents');
    }

    public function getCollectionName(): string
    {
        return match ($this->type) {
            self::TYPE_IMAGE => 'messenger-images',
            self::TYPE_VIDEO => 'messenger-videos',
            self::TYPE_AUDIO => 'messenger-audios',
            self::TYPE_FILE => 'messenger-files',
            self::TYPE_DOCUMENT => 'messenger-documents',
            default => 'messenger-files',
        };
    }
}
