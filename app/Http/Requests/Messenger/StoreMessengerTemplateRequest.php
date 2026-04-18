<?php

namespace App\Http\Requests\Messenger;

use App\Models\MessengerTemplate;
use Illuminate\Foundation\Http\FormRequest;

class StoreMessengerTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Clean up empty url fields from buttons (postback and phone_number don't need url)
        if ($this->has('payload.elements')) {
            $payload = $this->input('payload');
            foreach ($payload['elements'] as $eIndex => $element) {
                if (isset($element['buttons'])) {
                    foreach ($element['buttons'] as $bIndex => $button) {
                        if (isset($button['url']) && $button['url'] === '') {
                            unset($payload['elements'][$eIndex]['buttons'][$bIndex]['url']);
                        }
                    }
                }
            }
            $this->merge(['payload' => $payload]);
        }

        if ($this->has('payload.buttons')) {
            $payload = $this->input('payload');
            foreach ($payload['buttons'] as $bIndex => $button) {
                if (isset($button['url']) && $button['url'] === '') {
                    unset($payload['buttons'][$bIndex]['url']);
                }
            }
            $this->merge(['payload' => $payload]);
        }
    }

    public function rules(): array
    {
        $type = $this->input('type');

        $rules = [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:' . implode(',', MessengerTemplate::TYPES),
            'payload' => 'required|array',
            'is_active' => 'sometimes|boolean',
            // Media files - images for elements
            'images' => 'nullable|array',
            'images.*' => 'file|mimes:jpeg,jpg,png,gif,webp|max:10240',
            // Media file for media template
            'media_file' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp,mp4|max:25600',
        ];

        // Add type-specific validation
        return array_merge($rules, $this->getPayloadRulesForType($type));
    }

    private function getPayloadRulesForType(?string $type): array
    {
        return match ($type) {
            MessengerTemplate::TYPE_GENERIC => $this->genericTemplateRules(),
            MessengerTemplate::TYPE_BUTTON => $this->buttonTemplateRules(),
            MessengerTemplate::TYPE_MEDIA => $this->mediaTemplateRules(),
            MessengerTemplate::TYPE_RECEIPT => $this->receiptTemplateRules(),
            MessengerTemplate::TYPE_COUPON => $this->couponTemplateRules(),
            default => [],
        };
    }

    private function genericTemplateRules(): array
    {
        return [
            'payload.elements' => 'required|array|min:1|max:10',
            'payload.elements.*.title' => 'required|string|max:80',
            'payload.elements.*.subtitle' => 'nullable|string|max:80',
            'payload.elements.*.image_url' => 'nullable|string', // Can be URL or will be set from uploaded file
            'payload.elements.*.default_action' => 'nullable|array',
            'payload.elements.*.default_action.type' => 'required_with:payload.elements.*.default_action|in:web_url',
            'payload.elements.*.default_action.url' => 'required_with:payload.elements.*.default_action|url',
            'payload.elements.*.buttons' => 'nullable|array|max:3',
            'payload.elements.*.buttons.*.type' => 'required|in:web_url,postback,phone_number',
            'payload.elements.*.buttons.*.title' => 'required|string|max:20',
            'payload.elements.*.buttons.*.url' => 'nullable|url',
            'payload.elements.*.buttons.*.payload' => 'nullable|string|max:1000',
        ];
    }

    private function buttonTemplateRules(): array
    {
        return [
            'payload.text' => 'required|string|max:640',
            'payload.buttons' => 'required|array|min:1|max:3',
            'payload.buttons.*.type' => 'required|in:web_url,postback,phone_number',
            'payload.buttons.*.title' => 'required|string|max:20',
            'payload.buttons.*.url' => 'nullable|url',
            'payload.buttons.*.payload' => 'nullable|string|max:1000',
        ];
    }

    private function mediaTemplateRules(): array
    {
        return [
            'payload.elements' => 'required|array|size:1',
            'payload.elements.*.media_type' => 'required|in:image,video',
            'payload.elements.*.url' => 'nullable|string', // Can be URL or will be set from uploaded file
            'payload.elements.*.attachment_id' => 'nullable|string',
            'payload.elements.*.buttons' => 'nullable|array|max:1',
            'payload.elements.*.buttons.*.type' => 'required|in:web_url,postback',
            'payload.elements.*.buttons.*.title' => 'required|string|max:20',
        ];
    }

    private function receiptTemplateRules(): array
    {
        return [
            'payload.recipient_name' => 'required|string',
            'payload.order_number' => 'required|string',
            'payload.currency' => 'required|string|size:3',
            'payload.payment_method' => 'required|string',
            'payload.summary' => 'required|array',
            'payload.summary.total_cost' => 'required|numeric|min:0',
            'payload.elements' => 'nullable|array',
            'payload.elements.*.title' => 'required|string',
            'payload.elements.*.quantity' => 'required|integer|min:1',
            'payload.elements.*.price' => 'required|numeric|min:0',
        ];
    }

    private function couponTemplateRules(): array
    {
        return [
            'payload.title' => 'required|string|max:80',
            'payload.subtitle' => 'nullable|string|max:80',
            'payload.coupon_code' => 'required_without:payload.coupon_url|nullable|string|regex:/^\S+$/',
            'payload.coupon_url' => 'required_without:payload.coupon_code|nullable|url',
            'payload.coupon_url_button_title' => 'nullable|string|max:25',
            'payload.coupon_pre_message' => 'nullable|string',
            'payload.image_url' => 'nullable|string',
            'payload.payload' => 'nullable|string',
            // Coupon image file
            'coupon_image' => 'nullable|file|mimes:jpeg,jpg,png,gif,webp|max:10240',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateButtonFields($validator);
        });
    }

    private function validateButtonFields($validator): void
    {
        $buttons = [];

        // Collect buttons from elements (generic/media templates)
        if ($this->has('payload.elements')) {
            foreach ($this->input('payload.elements', []) as $eIndex => $element) {
                foreach ($element['buttons'] ?? [] as $bIndex => $button) {
                    $buttons[] = [
                        'button' => $button,
                        'path' => "payload.elements.{$eIndex}.buttons.{$bIndex}",
                    ];
                }
            }
        }

        // Collect buttons from payload.buttons (button template)
        if ($this->has('payload.buttons')) {
            foreach ($this->input('payload.buttons', []) as $bIndex => $button) {
                $buttons[] = [
                    'button' => $button,
                    'path' => "payload.buttons.{$bIndex}",
                ];
            }
        }

        // Validate each button based on its type
        foreach ($buttons as $item) {
            $button = $item['button'];
            $path = $item['path'];
            $type = $button['type'] ?? null;

            if ($type === 'web_url' && empty($button['url'])) {
                $validator->errors()->add("{$path}.url", 'URL is required for web_url button type');
            }

            if ($type === 'postback' && empty($button['payload'])) {
                $validator->errors()->add("{$path}.payload", 'Payload is required for postback button type');
            }

            if ($type === 'phone_number' && empty($button['payload'])) {
                $validator->errors()->add("{$path}.payload", 'Phone number is required for phone_number button type');
            }
        }
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required',
            'type.required' => 'Template type is required',
            'type.in' => 'Invalid template type. Must be one of: ' . implode(', ', MessengerTemplate::TYPES),
            'payload.required' => 'Template payload is required',
            'payload.elements.required' => 'At least one element is required',
            'payload.elements.*.title.required' => 'Element title is required',
            'payload.elements.*.title.max' => 'Element title must not exceed 80 characters',
            'payload.buttons.required' => 'At least one button is required for button template',
            'payload.buttons.*.title.max' => 'Button title must not exceed 20 characters',
            'payload.text.max' => 'Button template text must not exceed 640 characters',
        ];
    }
}
