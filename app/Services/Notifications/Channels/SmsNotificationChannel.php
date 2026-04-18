<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use App\Services\SendLoginNotificationService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Exception;

class SmsNotificationChannel implements NotificationChannelInterface
{
    protected SendLoginNotificationService $smsService;
    protected array $configuration;

    public function __construct(SendLoginNotificationService $smsService)
    {
        $this->smsService = $smsService;
        $this->configuration = config('notifications.channels.sms', []);
    }

    /**
     * Send a notification message via SMS
     */
    public function send(NotificationMessage $message, array $options = []): NotificationResult
    {
        try {
            if (!$this->isAvailable()) {
                return NotificationResult::failure(
                    'sms',
                    'SMS service is not available'
                );
            }
            $recipients = $this->extractPhoneNumbers($message);
            if (empty($recipients)) {
                return NotificationResult::failure(
                    'sms',
                    'No valid phone numbers found in recipients'
                );
            }

            $senderName = $this->getSenderName($message, $options);
            $content = $this->prepareContent($message, $options);
            
            $results = [];
            foreach ($recipients as $recipient) {
                try {
                    // تحديد نوع المرسل (admin أو user)
                    $senderType = $message->getData()['sender_type'] ?? 'user';

                    $this->smsService->sendSmsNotification(
                        $senderName,
                        $recipient['phone'],
                        $content,
                        $senderType, // استخدام sender_type بدلاً من recipient type
                        $recipient['user_id'] ?? null
                    );

                    $results[] = NotificationResult::success(
                        'sms',
                        $recipient['phone'],
                        $message->getId() . '_' . uniqid()
                    );

                    Log::info("SMS sent successfully", [
                        'message_id' => $message->getId(),
                        'recipient' => $recipient['phone'],
                        'sender' => $senderName,
                        'content_length' => mb_strlen($content, 'UTF-8')
                    ]);

                } catch (Exception $e) {
                    $results[] = NotificationResult::fromException('sms', $e, $recipient['phone']);
                    
                    Log::error("SMS send failed", [
                        'message_id' => $message->getId(),
                        'recipient' => $recipient['phone'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Return the first result for single recipient, or success if all succeeded
            if (count($results) === 1) {
                return $results[0];
            }

            $successCount = count(array_filter($results, fn($r) => $r->isSuccess()));
            $totalCount = count($results);

            if ($successCount === $totalCount) {
                return NotificationResult::success('sms', null, $message->getId())
                    ->addData('total_recipients', $totalCount)
                    ->addData('successful_sends', $successCount);
            } else {
                return NotificationResult::failure(
                    'sms',
                    "Partial failure: {$successCount}/{$totalCount} messages sent"
                )->addData('total_recipients', $totalCount)
                 ->addData('successful_sends', $successCount)
                 ->addData('failed_sends', $totalCount - $successCount);
            }

        } catch (Exception $e) {
            Log::error("SMS channel error", [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return NotificationResult::fromException('sms', $e);
        }
    }

    /**
     * Check if SMS channel is available
     */
    public function isAvailable(): bool
    {
        try {
            // Check if SMS service is configured
            $systemSender = Setting::get_by_name('system_sms_sender');
            return !empty($systemSender) && config('sms.notifications.sms', true);
        } catch (Exception $e) {
            Log::warning("SMS availability check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get channel name
     */
    public function getChannelName(): string
    {
        return 'sms';
    }

    /**
     * Get channel configuration
     */
    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->isAvailable(),
            'sender_name' => Setting::get_by_name('system_sms_sender'),
            'max_length' => $this->configuration['max_length'] ?? 160,
            'encoding' => $this->configuration['encoding'] ?? 'UTF-8',
            'rate_limit' => $this->configuration['rate_limit'] ?? null,
        ];
    }

    /**
     * Validate message for SMS channel
     */
    public function validateMessage(NotificationMessage $message): bool
    {
        $content = $message->getContent();
        $maxLength = $this->configuration['max_length'] ?? 160;

        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            Log::warning("SMS message too long", [
                'message_id' => $message->getId(),
                'length' => mb_strlen($content, 'UTF-8'),
                'max_length' => $maxLength
            ]);
            return false;
        }

        $recipients = $this->extractPhoneNumbers($message);
        if (empty($recipients)) {
            Log::warning("No valid phone numbers found", [
                'message_id' => $message->getId(),
                'recipients' => $message->getRecipients()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get channel limits
     */
    public function getLimits(): array
    {
        return [
            'max_message_length' => $this->configuration['max_length'] ?? 160,
            'max_recipients_per_request' => $this->configuration['max_recipients'] ?? 100,
            'rate_limit_per_minute' => $this->configuration['rate_limit_per_minute'] ?? 60,
            'rate_limit_per_hour' => $this->configuration['rate_limit_per_hour'] ?? 1000,
        ];
    }

    /**
     * Check if channel supports delivery confirmation
     */
    public function supportsDeliveryConfirmation(): bool
    {
        return $this->configuration['supports_delivery_confirmation'] ?? false;
    }

    /**
     * Extract phone numbers from message recipients
     */
    protected function extractPhoneNumbers(NotificationMessage $message): array
    {
        $phoneNumbers = [];
        
        foreach ($message->getRecipients() as $recipient) {
            switch ($recipient['type']) {
                case 'phone':
                    $phoneNumbers[] = [
                        'phone' => $recipient['identifier'],
                        'type' => 'direct',
                        'metadata' => $recipient['metadata'] ?? []
                    ];
                    break;
                    
                case 'user':
                    $user = \App\Models\User::find($recipient['identifier']);
                    if ($user && $user->number) {
                        $phoneNumbers[] = [
                            'phone' => $user->number,
                            'type' => 'user',
                            'user_id' => $user->id,
                            'metadata' => $recipient['metadata'] ?? []
                        ];
                    }
                    break;
            }
        }

        return $phoneNumbers;
    }

    /**
     * Get sender name for SMS
     */
    protected function getSenderName(NotificationMessage $message, array $options): string
    {
        // التحقق من نوع المرسل
        $senderType = $message->getData()['sender_type'] ?? 'user';

        if ($senderType === 'admin') {
            // استخدام اسم المرسل الخاص بالإدمن
            return $options['sender_name'] ??
                   $message->getMetadata()['sender_name'] ??
                   $message->getData()['sender_name'] ??
                   Setting::get_by_name('system_sms_sender') ??
                   'DREAMS';
        }

        // للمستخدمين العاديين، استخدام المرسل الخاص بهم
        $sender = $message->getSender();
        if ($sender) {
            try {
                $senderName = $sender->getAttribute('sender_name') ?? null;
                if (!empty($senderName)) {
                    return $senderName;
                }
            } catch (\Exception $e) {
                // تجاهل الخطأ والانتقال للـ fallback
            }
        }

        // fallback للإعدادات الافتراضية
        return $options['sender_name'] ??
               $message->getMetadata()['sender_name'] ??
               $message->getData()['sender_name'] ??
               Setting::get_by_name('system_sms_sender') ??
               'DREAMS';
    }

    /**
     * Prepare content for SMS
     */
    protected function prepareContent(NotificationMessage $message, array $options): string
    {
        $content = $message->getContent();
        $maxLength = $this->configuration['max_length'] ?? 160;

        // Truncate if too long
        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            $content = mb_substr($content, 0, $maxLength - 3, 'UTF-8') . '...';
        }

        return $content;
    }
}
