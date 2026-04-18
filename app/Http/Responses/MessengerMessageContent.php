<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\MessengerAttachmentMessage;
use App\Models\MessengerMessage;
use App\Models\MessengerTextMessage;

class MessengerMessageContent extends DataInterface
{
    public string $type;
    public array $content;

    public function __construct(MessengerMessage $message)
    {
        $this->type = $message->type;
        $messageable = $message->messageable;

        switch ($message->type) {
            case 'text':
                $this->formatTextMessage($messageable);
                break;

            case 'image':
            case 'video':
                $this->formatMediaMessage($messageable);
                break;

            case 'audio':
                $this->formatAudioMessage($messageable);
                break;

            case 'file':
            case 'document':
                $this->formatFileMessage($messageable);
                break;

            default:
                $this->content = $messageable?->toArray() ?? [];
                break;
        }
    }

    private function formatTextMessage(?MessengerTextMessage $messageable): void
    {
        $this->content = [
            'text' => $messageable->text ?? null,
            'preview_url' => $messageable->preview_url ?? false,
        ];

        if (isset($messageable->body_translate)) {
            $this->content['translated_text'] = $messageable->body_translate;
        }
    }

    private function formatMediaMessage(?MessengerAttachmentMessage $messageable): void
    {
        $this->content = [
            'media_id' => $messageable->media_id ?? null,
            'attachment_id' => $messageable->attachment_id ?? null,
            'link' => $messageable->url ?? null,
            'caption' => $messageable->caption ?? null,
            'preview_url' => $messageable->url ?? null,
            'filename' => $messageable->filename ?? null,
        ];
    }

    private function formatAudioMessage(?MessengerAttachmentMessage $messageable): void
    {
        $this->content = [
            'media_id' => $messageable->media_id ?? null,
            'attachment_id' => $messageable->attachment_id ?? null,
            'link' => $messageable->url ?? null,
            'preview_url' => $messageable->url ?? null,
            'filename' => $messageable->filename ?? null,
        ];
    }

    private function formatFileMessage(?MessengerAttachmentMessage $messageable): void
    {
        $this->content = [
            'media_id' => $messageable->media_id ?? null,
            'attachment_id' => $messageable->attachment_id ?? null,
            'link' => $messageable->url ?? null,
            'filename' => $messageable->filename ?? null,
            'caption' => $messageable->caption ?? null,
            'preview_url' => $messageable->url ?? null,
        ];
    }
}
