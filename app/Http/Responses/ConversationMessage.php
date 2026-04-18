<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\Channel;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatMessage;
use App\Models\LiveChatTextMessage;
use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaPage;
use App\Models\PostChatFormFieldResponse;
use App\Models\PreChatFormFieldResponse;
use App\Models\WhatsappMessage;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappPhoneNumber;
use App\Models\Widget;
use App\Models\MessageTranslation;
use App\Traits\Translation;
use App\Traits\WhatsappTemplateManager;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ConversationMessage extends DataInterface
{
    use Translation, WhatsappTemplateManager;

    /** @var string Message ID. */
    public string $id;

    /** @var string Conversation ID. */
    public string $conversation_id;


    /** @var string Message type (e.g., 'text', 'image', 'file'). */
    public string $type;

    /** @var bool|null Indicates if the message has been read. */
    public ?bool $is_read = null;

    /** @var string|null Timestamp of when the message was read (ISO 8601). */
    public ?string $read_at = null;

    /** @var string Message creation timestamp (ISO 8601). */
    public string $created_at;

    /** @var string Message last update timestamp (ISO 8601). */
    public string $updated_at;

    /** @var array Message-specific content. */
    public array $content;

    /** @var string|null For WhatsApp: direction of the message ('SENT' or 'RECEIVED'). */
    public ?string $direction = null;

    /** @var string|null Message status (e.g., 'sent', 'read'). */
    public ?string $status = null;

    /** @var array|null WhatsApp: history of status updates for the message. */
    public ?array $statuses = null;

    /** @var string|null Target language for translation, if enabled. */
    private ?string $translationLanguage = null;

    /** @var bool Whether message translation should be applied. */
    private bool $shouldTranslate = false;

    /** @var string The specific media or structure type of the message. */
    public string $message_type;

    /** @var WhatsappMessageContent|null The replied message object if this message is a reply. */
    public ?ConversationMessage $replied_message = null;

    public ?array $reaction_message;

    public ?array $sender;

    /** @var string|null The detected/source language of the message. */
    public ?string $detected_language = null;

    /** @var array Available translations for this message. */
    public array $translations = [];


    /**
     * Constructs a new ConversationMessage response.
     *
     * @param WhatsappMessage|LiveChatMessage|MessengerMessage $message Message model instance.
     * @param string $platform Messaging platform (e.g., 'whatsapp', 'livechat').
     * @param array $options Additional options like translation flags.
     */
    public function __construct(WhatsappMessage|LiveChatMessage|MessengerMessage $message, string $platform, array $options = [])
    {

        $this->id = $message->id;
        $this->type = $message->type;
        $this->status = $message->status;
        $this->sender = $this->getSender($message);

        $this->created_at = is_int($message->created_at)
            ? Carbon::createFromTimestamp($message->created_at)->toIso8601String()
            : optional($message->created_at)?->toIso8601String();

        $this->updated_at = is_int($message->updated_at)
            ? Carbon::createFromTimestamp($message->updated_at)->toIso8601String()
            : optional($message->updated_at)?->toIso8601String();

        $this->shouldTranslate = $options['translate'] ?? false;
        $this->translationLanguage = $options['lang'] ?? 'en';

        if ($message->conversation_id === null) {
            \Log::error('ConversationMessage: conversation_id is null', [
                'message_id' => $message->id,
                'message_type' => \get_class($message),
                'platform' => $platform,
                'trace' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10))
                    ->map(fn($t) => ($t['file'] ?? '') . ':' . ($t['line'] ?? '') . ' ' . ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? ''))
                    ->toArray()
            ]);
            throw new \InvalidArgumentException(
                "Message ID {$message->id} (" . \get_class($message) . ") has null conversation_id"
            );
        }

        $this->conversation_id = $message->conversation_id;

        $this->is_read = $message->status == 'read';
        $this->read_at = $this->getReadAtFromStatuses($message->statuses ?? null);

        if ($message->statuses && $message->statuses->count() > 0) {
            $this->statuses = $message->statuses->toArray();
        }

        $this->direction = $message->direction;
        $this->reaction_message = (isset($message->toArray()['reaction_message']) && !empty($message->toArray()['reaction_message'])) ? $message->toArray()['reaction_message'] : null;

        // Load translations and detected language
        $this->loadTranslations($message);

        $this->formatMessageContent($message, $platform);
    }


    /**
     * Format message content based on platform and message type
     *
     * @param Model $message
     * @param string $platform
     * @return void
     */
    private function formatMessageContent(Model $message, string $platform): void
    {
        if ($platform === Channel::LIVECHAT_PLATFORM) {
            $this->formatLiveChatContent($message);
        } elseif ($platform === Channel::WHATSAPP_PLATFORM) {
            $this->formatWhatsAppContent($message);
        } else if ($platform === Channel::MESSENGER_PLATFORM) {
            $this->formatMessengerContent($message);
        }
    }

    /**
     * Format LiveChat message content
     *
     * @param Model $message
     * @return void
     */
    private function formatLiveChatContent(Model $message): void
    {
        switch ($message->type) {
            case 'text':
                if ($message->messageable instanceof LiveChatTextMessage) {
                    $this->message_type = 'text';
                    $this->content = [
                        'text' => $message->messageable->text
                    ];
                }
                if ($message->repliedToMessage) {
                     $this->replied_message = new ConversationMessage($message->repliedToMessage, Channel::LIVECHAT_PLATFORM);
                }
                break;
            case 'file':
                if ($message->messageable instanceof LiveChatFileMessage) {
                    $media = $message->messageable->getFirstMedia('*');
                    if ($media && str_starts_with($media->mime_type, 'image/')) {
                        $this->message_type = 'image';
                        $this->content = [
                            'media_id' => $message->messageable->media_id,
                            'link' => $message->messageable->link,
                            'caption' => $message->messageable->caption,
                            'preview_url' => $message->messageable->getSignedMediaUrlForPreview(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    } else if ($media && str_starts_with($media->mime_type, 'video/')) {
                        $this->message_type = 'video';
                        $this->content = [
                            'media_id' => $message->messageable->media_id,
                            'link' => $message->messageable->link,
                            'caption' => $message->messageable->caption,
                            'preview_url' => $message->messageable->getSignedMediaUrlForPreview(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    } else {
                        $this->message_type = 'file';
                        $this->content = [
                            'media_id' => $message->messageable->media_id,
                            'link' => $message->messageable->link,
                            'caption' => $message->messageable->caption,
                            'preview_url' => $message->messageable->getMediaUrl(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    }
                }
                break;
            case 'pre_form_submission':

                if ($message->messageable instanceof PreChatFormFieldResponse) {
                    $this->message_type = 'pre_form_submission';
                    $allResponses = PreChatFormFieldResponse::getConversationResponses($message->conversation_id);
                    $this->content = [
                        'data' => $allResponses
                    ];
                }

            case 'post_form_submission':

                if ($message->messageable instanceof PostChatFormFieldResponse) {
                    $this->message_type = 'post_form_submission';
                    $allResponses = PostChatFormFieldResponse::getConversationResponses($message->conversation_id);
                    $this->content = [
                        'data' => $allResponses
                    ];
                }
                break;

            default:

                // For other message types, just include the messageable data if available
                $this->message_type = $message->type;
                $this->content = $message->messageable ? $message->messageable->toArray() : [];
                break;
        }
    }

    /**
     * Format WhatsApp message content
     *
     * @param WhatsappMessage $message
     * @return void
     */
    private function formatWhatsAppContent(WhatsappMessage $message): void
    {
        $content = new WhatsappMessageContent($message);
        $this->message_type = $content->type;
        $this->content = $content->content;
        if ($message->repliedToMessage) {
            $this->replied_message = new ConversationMessage($message->repliedToMessage, Channel::WHATSAPP_PLATFORM);
        } else {
            $this->replied_message = null;
        }
    }


    /**
     * Format Messenger message content
     *
     * @param MessengerMessage $message
     * @return void
     */
    private function formatMessengerContent(MessengerMessage $message): void
    {
        $content = new MessengerMessageContent($message);
        $this->message_type = $content->type;
        $this->content = $content->content;

        // Handle translation for text messages
        if ($message->type === 'text' && $this->shouldTranslate && !isset($this->content['translated_text'])) {
            $this->translateMessageContent($message);
        }

        // Handle replied messages
        if ($message->repliedToMessage) {
            $this->replied_message = new ConversationMessage($message->repliedToMessage, Channel::MESSENGER_PLATFORM);
        } else {
            $this->replied_message = null;
        }
    }

    /**
     * Translate message content
     *
     * @param Model $message
     * @return void
     */
    private function translateMessageContent(Model $message): void
    {
        if (!$this->shouldTranslate) {
            return;
        }

        $textToTranslate = null;

        if ($message->type === 'text' && $message->messageable && isset($message->messageable->body)) {
            $textToTranslate = $message->messageable->body;
        } elseif ($message->type === 'template') {
            $textToTranslate = $this->getTemplateBodyWithParameters($message);
        }

        if ($textToTranslate) {
            $translationResult = $this->translateText([$textToTranslate], $this->translationLanguage);

            if (!empty($translationResult['translations'][0])) {
                $this->content['translated_text'] = $translationResult['translations'][0];
            }
        }
    }

    /**
     * Load translations and detected language for the message.
     *
     * @param Model $message
     * @return void
     */
    private function loadTranslations(Model $message): void
    {
        // Get all available translations for this message
        $this->translations = MessageTranslation::getTranslationsArray($message);

        // Get the detected/source language
        $this->detected_language = MessageTranslation::getSourceLanguage($message);
    }

    /**
     * Extract the read timestamp from the message statuses.
     *
     * @param \Illuminate\Support\Collection|null $statuses
     * @return string|null
     */
    private function getReadAtFromStatuses(?\Illuminate\Support\Collection $statuses): ?string
    {
        if (!$statuses || $statuses->isEmpty()) {
            return null;
        }

        $readStatus = $statuses->firstWhere('status', 'read');
        if ($readStatus && isset($readStatus->timestamp)) {
            return Carbon::createFromTimestamp($readStatus->timestamp)->toIso8601String();
        }

        return null;
    }

    private function getSender($message)
    {
        // WhatsApp Consumer
        if ($message->sender_type === WhatsappConsumerPhoneNumber::class) {
            return [
                'type' => 'customer',
                'number' => optional($message->sender)->phone_number ?? null,
                'name' => optional($message->sender)->name ?? 'Customer',
            ];
        }

        // WhatsApp Business Phone
        if ($message->sender_type === WhatsappPhoneNumber::class) {
            return [
                'type' => 'agent',
                'number' => optional($message->sender)->display_phone_number ?? null,
                'name' => optional($message->agent)->name ?? 'Agent',
            ];
        }

        // Messenger Consumer
        if ($message->sender_type === MessengerConsumer::class) {
            return [
                'type' => 'customer',
                'psid' => optional($message->sender)->psid ?? null,
                'name' => optional($message->sender)->name ?? 'Customer',
            ];
        }

        // Messenger Meta Page (Business)
        if ($message->sender_type === MetaPage::class) {
            return [
                'type' => 'agent',
                'page_id' => optional($message->sender)->id ?? null,
                'name' => optional($message->agent)->name ?? 'Agent',
            ];
        }

        // LiveChat Contact Entity
        if ($message->sender_type === \App\Models\ContactEntity::class) {
            return [
                'type' => 'customer',
                'number' => optional($message->sender)->getPhoneIdentifier() ?? null,
                'name' => optional($message->sender)->getNameIdentifier(Channel::LIVECHAT_PLATFORM) ?? 'Customer',
            ];
        }

        // LiveChat Widget (Agent)
        if ($message->sender_type === Widget::class) {
            return [
                'type' => 'agent',
                'number' => optional($message->sender)->display_phone_number ?? null,
                'name' => optional($message->agent)->name ?? 'Agent',
            ];
        }

        // Default fallback
        $sender = $message->sender;

        return [
            'type' => $message->sender_type,
            'id' => $sender->id ?? '',
            'name' => $sender->name ?? '',
        ];
    }
}
