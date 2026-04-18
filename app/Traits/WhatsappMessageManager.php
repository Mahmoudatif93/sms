<?php

namespace App\Traits;

use App\Models\TemplateBodyCurrencyParameter;
use App\Models\TemplateBodyDateTimeParameter;
use App\Models\TemplateBodyTextParameter;
use App\Models\TemplateHeaderImageParameter;
use App\Models\TemplateMessageBodyComponent;
use App\Models\TemplateMessageHeaderComponent;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappMessage;
use App\Models\WhatsappMessageStatus;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappTemplateMessage;
use App\Services\WhatsAppMockService;
use Http;
use Illuminate\Database\Eloquent\Model;
use Log;

trait WhatsappMessageManager
{
    use WhatsappPhoneNumberManager;
    public function sendWhatsAppTemplateMessage($request, $accessToken, $toSendComponents, $templateName)
    {
        $toPhoneNumber = $request->get('to');
        $fromPhoneNumberId = $request->get('from');

        // Build the message payload
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toPhoneNumber,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => $request->get('language')
            ],
        ];

        // Add components only if there are variables
        if (!empty($toSendComponents)) {
            $payload['template']['components'] = $toSendComponents;
        }

        // Check if mock mode is enabled
        // if (config('whatsapp.mock.enabled', false) || $fromPhoneNumberId == '819922767865641') {
        //     Log::info('WhatsApp Mock: Sending template message', [
        //         'to' => $toPhoneNumber,
        //         'template_name' => $templateName,
        //         'from' => $fromPhoneNumberId,
        //         'components' => $toSendComponents
        //     ]);
        //     $mockService = app(WhatsAppMockService::class);
        //     return $mockService->sendTemplateMessage($payload, $accessToken, $fromPhoneNumberId);
        // }

        // Send the request to the WhatsApp API
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = 'v23.0';
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";
        $response = Http::withToken($accessToken)->post($url, $payload);


        if (!$response->successful()) {
            $error = json_decode($response->body())->error;
            $errorData = $error?->error_data?->details ?? '';

            return ['success' => false, 'error' => $error->message . '. ' . $errorData , 'status' => $response->status()];
        }

        return ['success' => true, 'data' => json_decode($response->body()), 'status' => $response->status()];
    }

    public function saveTemplateMessageAndComponents($request, $responseData, $toSendComponents, $template)
    {
        // Save the WhatsApp message and related data
        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData->messages[0]->id,
            'whatsapp_phone_number_id' => $request->get('from'),
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $request->get('from'),
            'recipient_id' => $this->getOrCreateRecipient($request->get('to'), $request->get('from'), $responseData->contacts[0]->wa_id)->id,
            'recipient_type' => WhatsappConsumerPhoneNumber::class,
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_TEMPLATE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'campaign_id' => $request->get('campaign_id') ?? null,
            'conversation_id' => $request->get('conversation_id') ?? null,
        ]);

        // Save the template message details
        $whatsappTemplateMessage = WhatsappTemplateMessage::create([
            'whatsapp_message_id' => $responseData->messages[0]->id,
            'whatsapp_template_id' => $template['id'],
            'template_name' => $template['name'],
            'template_language_code' => $template['language']
        ]);

        // Update the messageable relation
        $whatsappMessage->update([
            'messageable_id' => $whatsappTemplateMessage->id,
            'messageable_type' => WhatsappTemplateMessage::class,
        ]);

        if ($this->templateHasVariables($template)) {
            // Save the components and their parameters
            foreach ($toSendComponents as $component) {
                if($component['type'] == 'body') {
                    $bodyComponent = TemplateMessageBodyComponent::create([
                        'template_message_id' => $whatsappTemplateMessage->id,
                        'type' => $component['type'],
                    ]);

                    foreach ($component['parameters'] as $parameter) {
                        switch ($parameter['type']) {
                            case 'text':
                                TemplateBodyTextParameter::create([
                                    'template_message_body_component_id' => $bodyComponent->id,
                                    'text' => $parameter['text'],
                                ]);
                                break;
                            case 'currency':
                                TemplateBodyCurrencyParameter::create([
                                    'template_message_body_component_id' => $bodyComponent->id,
                                    'fallback_value' => $parameter['currency']['fallback_value'],
                                    'code' => $parameter['currency']['code'],
                                    'amount_1000' => $parameter['currency']['amount_1000'],
                                ]);
                                break;
                            case 'date_time':
                                TemplateBodyDateTimeParameter::create([
                                    'template_message_body_component_id' => $bodyComponent->id,
                                    'fallback_value' => $parameter['date_time']['fallback_value'] ?? null,
                                    'day_of_week' => $this->normalizeDayOfWeek($parameter['date_time']['day_of_week'] ?? null),
                                    'year' => $parameter['date_time']['year'] ?? null,
                                    'month' => $parameter['date_time']['month'] ?? null,
                                    'day_of_month' => $parameter['date_time']['day_of_month'] ?? null,
                                    'hour' => $parameter['date_time']['hour'] ?? null,
                                    'minute' => $parameter['date_time']['minute'] ?? null,
                                    'calendar' => $parameter['date_time']['calendar'] ?? 'GREGORIAN',
                                ]);
                                break;
                        }
                    }
                }elseif ($component['type'] == 'header') {
                    $headerComponent = TemplateMessageHeaderComponent::create([
                        'template_message_id' => $whatsappTemplateMessage->id,
                        'type' => $component['type'],
                    ]);

                    foreach ($component['parameters'] as $parameter) {
                        switch ($parameter['type']) {
                            case 'image':
                                TemplateHeaderImageParameter::create([
                                    'tmpl_msg_hdr_component_id' => $headerComponent->id,
                                    'link' => $parameter['image']['link'],
                                ]);
                                break;

                        }
                    }
                }elseif ($component['type'] == 'footer') {

                }
            }
        }

        // Save New Message Status
        $this->saveMessageStatus((string)$whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        // Eager load statuses and messageable relations
        $whatsappMessageWithRelations = WhatsappMessage::with(
            'statuses',
            'messageable',
            'messageable.bodyComponents',
            'messageable.bodyComponents.bodyTextParameters',
            'messageable.bodyComponents.bodyCurrencyParameters',
            'messageable.bodyComponents.bodyDateTimeParameters',
        )->find($whatsappMessage->id);

//        // Call the getTemplateBodyWithParameters function if the message type is template
//        if ($whatsappMessage->type === WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
//            $formattedMessage = $this->getTemplateBodyWithParameters($whatsappMessage);
//            $whatsappMessageWithRelations->formatted_message = $formattedMessage;
//        }

        return $whatsappMessageWithRelations;
    }

    public function getOrCreateRecipient($toPhoneNumber, $fromPhoneNumberId, $wa_id): Model|WhatsappConsumerPhoneNumber
    {
        $whatsappBusinessAccountID = WhatsappPhoneNumber::whereId($fromPhoneNumberId)->value('whatsapp_business_account_id');

        return WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountID,
            ],
            [
                'wa_id' => $wa_id,
            ]
        );
    }

    public function getRecipient($toPhoneNumber): Model|WhatsappConsumerPhoneNumber|null
    {
        return WhatsappConsumerPhoneNumber::where('phone_number' , '=' , $toPhoneNumber)->first()?? null;
    }

    /**
     * Helper Method to Save Message Status
     */
    private function saveMessageStatus(string $messageId, string $status, ?int $timestamp = null): void
    {
        WhatsappMessageStatus::updateOrCreate(
            [
                'whatsapp_message_id' => $messageId,
                'status' => $status,
            ],
            [
                'timestamp' => empty($timestamp) ? time() : $timestamp,
            ]
        );
    }

    // Helper: normalize day_of_week to 1..7 or null
    private function normalizeDayOfWeek($value): ?int
    {
        if ($value === null || $value === '') return null;

        // Accept integers or numeric strings
        if (is_numeric($value)) {
            $n = (int)$value;
            return ($n >= 1 && $n <= 7) ? $n : null;
        }

        // Accept day names
        static $map = [
            'MONDAY'    => 1, 'TUESDAY'   => 2, 'WEDNESDAY' => 3,
            'THURSDAY'  => 4, 'FRIDAY'    => 5, 'SATURDAY'  => 6,
            'SUNDAY'    => 7,
        ];
        $key = strtoupper(trim($value));
        return $map[$key] ?? null;
    }


}
