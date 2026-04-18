<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppMockService
{
    private array $sentMessages = [];
    private array $messageStatuses = [];

    /**
     * محاكاة إرسال رسالة template عبر WhatsApp API
     */
    public function sendTemplateMessage(array $payload, string $accessToken, string $fromPhoneNumberId): array
    {
        // محاكاة التأخير إذا كان مفعلاً
        $this->simulateDelay();

        // محاكاة الفشل العشوائي إذا كان مفعلاً
        if ($this->shouldSimulateFailure()) {
            return $this->simulateFailure();
        }

        // تسجيل الرسالة المرسلة للمراجعة
        $messageId = $this->generateMessageId();
        $contactWaId = $this->generateWaId($payload['to']);
        
        $mockMessage = [
            'id' => $messageId,
            'payload' => $payload,
            'access_token' => $accessToken,
            'from_phone_number_id' => $fromPhoneNumberId,
            'timestamp' => now(),
            'status' => 'sent'
        ];

        $this->sentMessages[] = $mockMessage;

        // تسجيل في اللوج للمراجعة إذا كان مفعلاً
        if (config('whatsapp.mock.log_messages', true)) {
            Log::info('WhatsApp Mock: Template message sent', [
                'message_id' => $messageId,
                'to' => $payload['to'],
                'template_name' => $payload['template']['name'] ?? 'unknown',
                'from' => $fromPhoneNumberId,
                'components' => $payload['template']['components'] ?? null
            ]);
        }

        // محاكاة استجابة Facebook Graph API الناجحة
        return [
            'success' => true,
            'data' => (object) [
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    (object) [
                        'input' => $payload['to'],
                        'wa_id' => $contactWaId
                    ]
                ],
                'messages' => [
                    (object) [
                        'id' => $messageId
                    ]
                ]
            ],
            'status' => 200
        ];
    }

    /**
     * محاكاة إرسال رسالة نصية عبر WhatsApp API
     */
    public function sendTextMessage(array $payload, string $accessToken, string $fromPhoneNumberId): array
    {
        // محاكاة التأخير إذا كان مفعلاً
        $this->simulateDelay();

        // محاكاة الفشل العشوائي إذا كان مفعلاً
        if ($this->shouldSimulateFailure()) {
            return $this->simulateFailure();
        }

        $messageId = $this->generateMessageId();
        $contactWaId = $this->generateWaId($payload['to']);
        
        $mockMessage = [
            'id' => $messageId,
            'payload' => $payload,
            'access_token' => $accessToken,
            'from_phone_number_id' => $fromPhoneNumberId,
            'timestamp' => now(),
            'status' => 'sent'
        ];

        $this->sentMessages[] = $mockMessage;

        if (config('whatsapp.mock.log_messages', true)) {
            Log::info('WhatsApp Mock: Text message sent', [
                'message_id' => $messageId,
                'to' => $payload['to'],
                'text' => $payload['text']['body'] ?? 'unknown',
                'from' => $fromPhoneNumberId
            ]);
        }

        return [
            'success' => true,
            'data' => (object) [
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    (object) [
                        'input' => $payload['to'],
                        'wa_id' => $contactWaId
                    ]
                ],
                'messages' => [
                    (object) [
                        'id' => $messageId
                    ]
                ]
            ],
            'status' => 200
        ];
    }

    /**
     * محاكاة فشل في الإرسال (للاختبار)
     */
    public function simulateFailure(string $errorType = 'rate_limit'): array
    {
        $errors = [
            'rate_limit' => [
                'message' => 'Rate limit exceeded',
                'code' => 80007,
                'error_subcode' => 2494055
            ],
            'invalid_phone' => [
                'message' => 'Invalid phone number',
                'code' => 100,
                'error_subcode' => 33
            ],
            'template_not_found' => [
                'message' => 'Template not found',
                'code' => 132000,
                'error_subcode' => 2494002
            ]
        ];

        $error = $errors[$errorType] ?? $errors['rate_limit'];

        return [
            'success' => false,
            'error' => $error['message'],
            'status' => 400
        ];
    }

    /**
     * الحصول على جميع الرسائل المرسلة (للمراجعة والاختبار)
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * مسح جميع الرسائل المحفوظة
     */
    public function clearSentMessages(): void
    {
        $this->sentMessages = [];
        $this->messageStatuses = [];
    }

    /**
     * توليد معرف رسالة وهمي
     */
    private function generateMessageId(): string
    {
        return 'wamid.mock_' . Str::random(32);
    }

    /**
     * توليد WhatsApp ID وهمي
     */
    private function generateWaId(string $phoneNumber): string
    {
        // إزالة الرموز وإبقاء الأرقام فقط
        return preg_replace('/[^0-9]/', '', $phoneNumber);
    }

    /**
     * محاكاة تحديث حالة الرسالة
     */
    public function updateMessageStatus(string $messageId, string $status): void
    {
        $this->messageStatuses[$messageId] = [
            'status' => $status,
            'timestamp' => now()
        ];

        Log::info('WhatsApp Mock: Message status updated', [
            'message_id' => $messageId,
            'status' => $status
        ]);
    }

    /**
     * محاكاة التأخير في الاستجابة
     */
    private function simulateDelay(): void
    {
        if (config('whatsapp.mock.simulate_delays', false)) {
            $minDelay = config('whatsapp.mock.delay_min', 100);
            $maxDelay = config('whatsapp.mock.delay_max', 500);
            $delay = rand($minDelay, $maxDelay);

            // تحويل إلى ثواني للـ sleep
            usleep($delay * 1000);
        }
    }

    /**
     * تحديد ما إذا كان يجب محاكاة فشل
     */
    private function shouldSimulateFailure(): bool
    {
        $failureRate = config('whatsapp.mock.failure_rate', 0);

        if ($failureRate <= 0) {
            return false;
        }

        return rand(1, 100) <= $failureRate;
    }

    /**
     * الحصول على إحصائيات المحاكي
     */
    public function getStats(): array
    {
        return [
            'total_messages' => count($this->sentMessages),
            'successful_messages' => count(array_filter($this->sentMessages, fn($msg) => $msg['status'] === 'sent')),
            'failed_messages' => count(array_filter($this->sentMessages, fn($msg) => $msg['status'] === 'failed')),
            'last_message_time' => !empty($this->sentMessages) ? end($this->sentMessages)['timestamp'] : null
        ];
    }
}
