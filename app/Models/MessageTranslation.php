<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Class MessageTranslation
 *
 * Stores translated text for messages to avoid re-translating.
 *
 * @property int $id Primary key
 * @property string $messageable_id The ID of the polymorphic message
 * @property string $messageable_type The class name of the polymorphic message model
 * @property string|null $source_language The detected source language
 * @property string $target_language The target language for translation
 * @property string $translated_text The translated text content
 * @property Carbon|null $created_at Timestamp when the record was created
 * @property Carbon|null $updated_at Timestamp when the record was last updated
 *
 * @property-read Model|MorphTo $messageable The polymorphic related model
 *
 * @method static Builder|MessageTranslation newModelQuery()
 * @method static Builder|MessageTranslation newQuery()
 * @method static Builder|MessageTranslation query()
 * @method static Builder|MessageTranslation whereId($value)
 * @method static Builder|MessageTranslation whereMessageableId($value)
 * @method static Builder|MessageTranslation whereMessageableType($value)
 * @method static Builder|MessageTranslation whereSourceLanguage($value)
 * @method static Builder|MessageTranslation whereTargetLanguage($value)
 * @method static Builder|MessageTranslation whereTranslatedText($value)
 * @method static Builder|MessageTranslation whereCreatedAt($value)
 * @method static Builder|MessageTranslation whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class MessageTranslation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'message_translations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'messageable_id',
        'messageable_type',
        'source_language',
        'target_language',
        'translated_text',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the related model for translation (e.g., WhatsappMessage, LiveChatMessage).
     *
     * @return MorphTo
     */
    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get translation for a specific message and target language.
     *
     * @param Model $message
     * @param string $targetLanguage
     * @return MessageTranslation|null
     */
    public static function getTranslation(Model $message, string $targetLanguage): ?self
    {
        return self::where('messageable_id', $message->getKey())
            ->where('messageable_type', get_class($message))
            ->where('target_language', $targetLanguage)
            ->first();
    }

    /**
     * Check if translation exists for a specific message and target language.
     *
     * @param Model $message
     * @param string $targetLanguage
     * @return bool
     */
    public static function hasTranslation(Model $message, string $targetLanguage): bool
    {
        return self::where('messageable_id', $message->getKey())
            ->where('messageable_type', get_class($message))
            ->where('target_language', $targetLanguage)
            ->exists();
    }

    /**
     * Get all translations for a specific message.
     *
     * @param Model $message
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getAllTranslations(Model $message): \Illuminate\Database\Eloquent\Collection
    {
        return self::where('messageable_id', $message->getKey())
            ->where('messageable_type', get_class($message))
            ->get();
    }

    /**
     * Get all translations for a message formatted as an array.
     *
     * @param Model $message
     * @return array
     */
    public static function getTranslationsArray(Model $message): array
    {
        $translations = self::getAllTranslations($message);

        $result = [];
        foreach ($translations as $translation) {
            $result[] = [
                'target_language' => $translation->target_language,
                'translated_text' => $translation->translated_text,
            ];
        }

        return $result;
    }

    /**
     * Get the source language of the first translation (detected language).
     *
     * @param Model $message
     * @return string|null
     */
    public static function getSourceLanguage(Model $message): ?string
    {
        return self::where('messageable_id', $message->getKey())
            ->where('messageable_type', get_class($message))
            ->whereNotNull('source_language')
            ->value('source_language');
    }
}
