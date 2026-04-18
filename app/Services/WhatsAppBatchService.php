<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Traits\WhatsappTemplateManager;

class WhatsAppBatchService
{
    use WhatsappTemplateManager;

    /**
     * إرسال Batch Request إلى Meta
     */
    public function sendBatch(array $messages): array
    {
        if (empty($messages)) {
            return [
                'success' => false,
                'error' => 'No messages to send',
                'results' => []
            ];
        }

        // Meta تسمح بحد أقصى 50 رسالة في batch
        if (count($messages) > 50) {
            throw new Exception('Batch size cannot exceed 50 messages');
        }

        $accessToken = $messages[0]['accessToken'] ?? null;
        if (!$accessToken) {
            throw new Exception('Access token is required');
        }

        $batchRequests = [];
        foreach ($messages as $index => $message) {
            $batchRequests[] = $this->buildBatchRequest($message, $index);
        }

        try {
            $baseUrl = rtrim(env('FACEBOOK_GRAPH_API_BASE_URL', 'https://graph.facebook.com'), '/');

            $response = Http::asForm()->post("$baseUrl/v23.0", [
                'access_token' => $accessToken,
                'include_headers' => false,
                'batch' => json_encode($batchRequests, JSON_UNESCAPED_SLASHES),
            ]);

            Log::info('WhatsApp Batch API Request', [
                'batch_size' => count($batchRequests),
                'url' => "$baseUrl/v23.0"
            ]);

            if (!$response->successful()) {
                Log::error('WhatsApp Batch API Error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'Batch request failed',
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'results' => []
                ];
            }

            $results = $this->processBatchResponse($response->json(), $messages);

            return [
                'success' => true,
                'results' => $results
            ];

        } catch (\Throwable $e) {
            Log::error('WhatsApp Batch Send Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => []
            ];
        }
    }

    /**
     * بناء طلب واحد داخل الـ Batch
     */
    private function buildBatchRequest(array $message, int $index): array
    {
        $fromPhoneNumberId = $message['fromPhoneNumberId'];
        $phoneNumber = $message['phoneNumber'];
        $templateData = $message['template'];

        // بناء الـ template object
        $template = [
            'name' => $templateData['template']['name'],
            'language' => [
                'code' => $templateData['template']['language']
            ]
        ];

        // فحص إذا كان الـ template يحتوي على متغيرات
        if ($this->templateHasVariables($templateData['template'])) {
            // استخدام الـ components المُجهزة مسبقاً من الـ message
            if (!empty($message['components'])) {
                $template['components'] = $message['components'];
            }
        }
        $body =
            "messaging_product=whatsapp" .
            "&to=" . urlencode($phoneNumber) .
            "&type=template" .
            "&template=" . urlencode(json_encode($template));
            
        return [
            'method' => 'POST',
            'relative_url' => "v23.0/$fromPhoneNumberId/messages",
            'body' => $body,
            'name' => "msg_$index"
        ];
    }

    /**
     * تحويل الـ components إلى الصيغة الصحيحة لكل نوع
     */
    private function normalizeComponents(array $components): array
    {
        $normalized = [];
        foreach ($components as $component) {
            $type = strtoupper($component['type'] ?? '');
            switch ($type) {
                case 'HEADER':
                    if (!empty($component['example']['header_handle'][0])) {
                        $normalized[] = [
                            'type' => 'header',
                            'parameters' => [
                                [
                                    'type' => 'image', // فقط مثال، يمكن تغييره حسب نوع الملف
                                    'image' => ['link' => $component['example']['header_handle'][0]]
                                ]
                            ]
                        ];
                    }
                    break;

                case 'BODY':
                    if (!empty($component['text'])) {
                        $normalized[] = [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $component['text']
                                ]
                            ]
                        ];
                    }
                    break;

                case 'FOOTER':
                    if (!empty($component['text'])) {
                        $normalized[] = [
                            'type' => 'footer',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $component['text']
                                ]
                            ]
                        ];
                    }
                    break;

                case 'BUTTONS':
                    if (!empty($component['buttons'])) {
                        foreach ($component['buttons'] as $i => $button) {
                            $buttonType = strtolower($button['type'] ?? 'url');
                            $normalized[] = [
                                'type' => 'button',
                                'sub_type' => $buttonType,
                                'index' => $i,
                                'parameters' => [
                                    ['type' => 'text', 'text' => $button['text'] ?? '']
                                ]
                            ];
                        }
                    }
                    break;
            }
        }

        return $normalized;
    }

    /**
     * معالجة رد الـ Batch
     */
    private function processBatchResponse(array $batchResponse, array $messages): array
    {
        $results = [];

        foreach ($batchResponse as $index => $response) {
            $code = $response['code'] ?? 500;
            $body = isset($response['body']) ? json_decode($response['body'], true) : null;

            $results[$index] = [
                'phoneNumber' => $messages[$index]['phoneNumber'],
                'messageLogId' => $messages[$index]['messageLogId'] ?? null,
                'contactId' => $messages[$index]['contactId'] ?? null,
                'code' => $code,
                'success' => ($code >= 200 && $code < 300),
                'data' => $body
            ];
        }

        return $results;
    }
}
