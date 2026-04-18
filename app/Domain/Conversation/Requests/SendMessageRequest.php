<?php

namespace App\Domain\Conversation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => 'required|string|in:text,image,video,audio,document,location,template,interactive,flow,files,reaction',
            'conversation_id' => 'sometimes|uuid|exists:conversations,id',
        ];

        // Conditional rules based on message type
        $type = $this->input('type');

        switch ($type) {
            case 'text':
                $rules['text.body'] = 'required_without:message|string|max:4096';
                $rules['message'] = 'required_without:text.body|string|max:4096';
                $rules['text.preview_url'] = 'nullable|boolean';
                break;

            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                $rules['media.link'] = 'required_without:media.id|url';
                $rules['media.id'] = 'required_without:media.link|string';
                $rules['media.caption'] = 'nullable|string|max:1024';
                break;

            case 'location':
                $rules['location.latitude'] = 'required|numeric|between:-90,90';
                $rules['location.longitude'] = 'required|numeric|between:-180,180';
                $rules['location.name'] = 'nullable|string|max:255';
                $rules['location.address'] = 'nullable|string|max:255';
                break;

            case 'template':
                $rules['template_id'] = 'required';
                $rules['template.components'] = 'nullable|array';
                break;

            case 'interactive':
                $rules['interactive.type'] = 'required|string|in:button,list,product,product_list';
                $rules['interactive.body'] = 'required|array';
                break;

            case 'files':
                $rules['files'] = 'required|array|min:1';
                $rules['files.*.file'] = 'required|file|max:10240';
                $rules['files.*.type'] = 'required|string|in:image,video,audio,document';
                $rules['files.*.caption'] = 'nullable|string|max:1000';
                break;

            case 'reaction':
                $rules['reaction.message_id'] = 'required|string';
                $rules['reaction.emoji'] = 'nullable|string|max:10';
                break;
        }

        // Reply context
        $rules['context.message_id'] = 'nullable|string';
        $rules['reply_to_message_id'] = 'nullable|string';

        return $rules;
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Message type is required.',
            'type.in' => 'Invalid message type specified.',
            'text.body.required_without' => 'Message text is required for text messages.',
            'template_id.required' => 'Template ID is required for template messages.',
            'files.required' => 'Files are required for file messages.',
            'location.latitude.required' => 'Latitude is required for location messages.',
            'location.longitude.required' => 'Longitude is required for location messages.',
        ];
    }
}
