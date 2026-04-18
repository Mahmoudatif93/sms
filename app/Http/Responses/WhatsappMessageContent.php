<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Traits\WhatsappTemplateManager;
use App\Models\WhatsappFlowMessage;
use App\Models\WhatsappFlowResponseMessage;
use App\Models\WhatsappInteractiveMessage;

class WhatsappMessageContent extends DataInterface
{
    use WhatsappTemplateManager;

    public string $type;
    public array $content;

    public function __construct(\App\Models\WhatsappMessage $message)
    {
        $this->type = $message->type;
        $messageable = $message->messageable;

        switch ($message->type) {
            case 'text':
                $this->content = [
                    'text' => $messageable->body ?? null,
                    'preview_url' => $messageable->preview_url ?? false,
                ];
                break;

            case 'video':
            case 'image':
                $this->content = [
                    'media_id' => $messageable->media_id ?? null,
                    'link' => $messageable->link ?? null,
                    'caption' => $messageable->caption ?? null,
                    'preview_url' => $messageable?->link ?? null,
                ];
                break;

            case 'audio':
                $this->content = [
                    'media_id' => $messageable->media_id ?? null,
                    'link' => $messageable->link ?? null,
                    'preview_url' => $messageable?->link ?? null,
                ];
                break;

            case 'document':
                $this->content = [
                    'media_id' =>  $messageable->media_id ?? null,
                    'link' =>  $messageable?->link ?? null,//$messageable?->link ??
                    'filename' =>  $messageable->filename ?? null,
                    'caption' => $messageable->caption ?? null,
                    'preview_url' => $messageable?->link ?? null,
                ];
                break;

            // case 'document':
            //     $this->content = [
            //         'media_id' => $messageable->media_id ?? null,
            //         'link' => $messageable->link ?? null,
            //         'filename' => $messageable->filename ?? null,
            //         'caption' => $messageable->caption ?? null,
            //         'preview_url' => $messageable->getSignedMediaUrlForPreview() ?? null,
            //     ];
            //     break;

            case 'template':
                $this->content = [
                    'template_locale' => $messageable->whatsappTemplate->language ?? null,
                    'formatted_header' => $this->getTemplateHeaderWithParameters($message),
                    'formatted_body' => $this->getTemplateBodyWithParameters($message),
                    'formatted_footer' => $this->getFooter($message),
                    'formatted_buttons' => $this->getTemplateButtons($message),
                ];
                break;

            case 'interactive':

                if ($messageable instanceof WhatsappFlowMessage) {
                    $this->content = [
                        'formatted_header' => $messageable->header_text ?? null,
                        'formatted_body' => $messageable->body_text ?? null,
                        'formatted_footer' => $messageable->footer_text ?? null,
                        'formatted_buttons' => $messageable->flow_cta ?? null,
                    ];
                } elseif ($messageable instanceof WhatsappFlowResponseMessage) {

                    $this->content = [
                        'formatted_body' => 'Response Sent'
                    ];
                }
                elseif ($messageable instanceof WhatsappInteractiveMessage && ($messageable->interactive_type === 'button' || $messageable->interactive_type === 'list') ) {
                   $this->content = $messageable?->toArray() ?? [];
                }
                elseif ($messageable instanceof WhatsappInteractiveMessage && ($messageable->interactive_type === 'button_reply' || $messageable->interactive_type === 'list_reply')) {
                    $this->content = [
                       'text' => $messageable->button_reply_title ?? $messageable->list_reply_title ?? null,
                    ];
                }

                else {
                    // dd($messageable);
                    $this->content = $messageable?->toArray() ?? [];
                }
                break;

            case 'reaction':
                $this->content = [
                    'message_id' => $messageable->message_id ?? null,
                    'emoji' => $messageable->emoji ?? null,
                ];
                break;

            default:
                $this->content = $messageable?->toArray() ?? [];
                break;
        }

    }
}
