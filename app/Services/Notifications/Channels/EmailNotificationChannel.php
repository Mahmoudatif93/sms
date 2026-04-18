<?php

namespace App\Services\Notifications\Channels;

use App\Contracts\NotificationChannelInterface;
use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use App\Services\SendLoginNotificationService;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailNotificationChannel implements NotificationChannelInterface
{
    protected SendLoginNotificationService $emailService;
    protected array $configuration;

    public function __construct(SendLoginNotificationService $emailService)
    {
        $this->emailService = $emailService;
        $this->configuration = config('notifications.channels.email', []);
    }

    /**
     * Send a notification message via Email
     */
    public function send(NotificationMessage $message, array $options = []): NotificationResult
    {
        try {
            if (!$this->isAvailable()) {
                return NotificationResult::failure(
                    'email',
                    'Email service is not available'
                );
            }

            $recipients = $this->extractEmailAddresses($message);
            if (empty($recipients)) {
                return NotificationResult::failure(
                    'email',
                    'No valid email addresses found in recipients'
                );
            }

            $subject = $this->getSubject($message, $options);
            $title = $this->getTitle($message, $options);
            $content = $this->prepareContent($message, $options);
            $viewName = $this->getViewName($message, $options);
            $results = [];
            foreach ($recipients as $recipient) {
                try {
                    $this->emailService->sendEmailNotification(
                        $recipient['email'],
                        $subject,
                        $title,
                        $content,
                        $viewName,
                        $options['attachments'] ?? [],
                        $options['inline_attachments'] ?? [],
                        $options['cc'] ?? [],
                        $options['bcc'] ?? [],
                        $message->getTemplateVariables(),
                        $message->getType()
                    );

                    $results[] = NotificationResult::success(
                        'email',
                        $recipient['email'],
                        $message->getId() . '_' . uniqid()
                    );

                    Log::info("Email sent successfully", [
                        'message_id' => $message->getId(),
                        'recipient' => $recipient['email'],
                        'subject' => $subject,
                        'view' => $viewName
                    ]);

                } catch (Exception $e) {
                    $results[] = NotificationResult::fromException('email', $e, $recipient['email']);
                    
                    Log::error("Email send failed", [
                        'message_id' => $message->getId(),
                        'recipient' => $recipient['email'],
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
                return NotificationResult::success('email', null, $message->getId())
                    ->addData('total_recipients', $totalCount)
                    ->addData('successful_sends', $successCount);
            } else {
                return NotificationResult::failure(
                    'email',
                    "Partial failure: {$successCount}/{$totalCount} emails sent"
                )->addData('total_recipients', $totalCount)
                 ->addData('successful_sends', $successCount)
                 ->addData('failed_sends', $totalCount - $successCount);
            }

        } catch (Exception $e) {
            Log::error("Email channel error", [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return NotificationResult::fromException('email', $e);
        }
    }

    /**
     * Check if Email channel is available
     */
    public function isAvailable(): bool
    {
      
        try {
            // Check if email service is configured
            $mailHost = Setting::get_by_name('smtp_host');
            $fromEmail = Setting::get_by_name('site_email');
            return !empty($mailHost) && 
                   !empty($fromEmail) && 
                   config('sms.notifications.email', true);
        } catch (Exception $e) {
            Log::warning("Email availability check failed", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get channel name
     */
    public function getChannelName(): string
    {
        return 'email';
    }

    /**
     * Get channel configuration
     */
    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->isAvailable(),
            'from_email' => Setting::get_by_name('from_email'),
            'from_name' => Setting::get_by_name('site_name') ?? 'Dreams SMS',
            'mail_host' => Setting::get_by_name('mail_host'),
            'supports_html' => true,
            'supports_attachments' => true,
            'max_attachment_size' => $this->configuration['max_attachment_size'] ?? '10MB',
        ];
    }

    /**
     * Validate message for Email channel
     */
    public function validateMessage(NotificationMessage $message): bool
    {
        $recipients = $this->extractEmailAddresses($message);
        if (empty($recipients)) {
            Log::warning("No valid email addresses found", [
                'message_id' => $message->getId(),
                'recipients' => $message->getRecipients()
            ]);
            return false;
        }
        $subject = $message->getTitle() ?? $message->getType();
        if (empty($subject)) {
            Log::warning("Email subject is empty", [
                'message_id' => $message->getId()
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
            'max_recipients_per_request' => $this->configuration['max_recipients'] ?? 50,
            'max_attachment_size' => $this->configuration['max_attachment_size'] ?? '10MB',
            'max_attachments' => $this->configuration['max_attachments'] ?? 10,
            'rate_limit_per_minute' => $this->configuration['rate_limit_per_minute'] ?? 30,
            'rate_limit_per_hour' => $this->configuration['rate_limit_per_hour'] ?? 500,
        ];
    }

    /**
     * Check if channel supports delivery confirmation
     */
    public function supportsDeliveryConfirmation(): bool
    {
        return $this->configuration['supports_delivery_confirmation'] ?? true;
    }

    /**
     * Extract email addresses from message recipients
     */
    protected function extractEmailAddresses(NotificationMessage $message): array
    {
        $emailAddresses = [];
        
        foreach ($message->getRecipients() as $recipient) {
            switch ($recipient['type']) {
                case 'email':
                    if (filter_var($recipient['identifier'], FILTER_VALIDATE_EMAIL)) {
                        $emailAddresses[] = [
                            'email' => $recipient['identifier'],
                            'type' => 'direct',
                            'metadata' => $recipient['metadata'] ?? []
                        ];
                    }
                    break;
                    
                case 'user':
                    $user = \App\Models\User::find($recipient['identifier']);
                    if ($user && $user->email && filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                        $emailAddresses[] = [
                            'email' => $user->email,
                            'type' => 'user',
                            'user_id' => $user->id,
                            'name' => $user->name ?? '',
                            'metadata' => $recipient['metadata'] ?? []
                        ];
                    }
                    break;
            }
        }

        return $emailAddresses;
    }

    /**
     * Get email subject
     */
    protected function getSubject(NotificationMessage $message, array $options): string
    {
        return $options['subject'] ?? 
               $message->getTitle() ?? 
               $message->getMetadata()['subject'] ?? 
               ucfirst(str_replace('_', ' ', $message->getType()));
    }

    /**
     * Get email title
     */
    protected function getTitle(NotificationMessage $message, array $options): string
    {
        return $options['title'] ?? 
               $message->getMetadata()['title'] ?? 
               Setting::get_by_name('site_name') ?? 
               'Dreams SMS';
    }

    /**
     * Get view name for email template
     */
    protected function getViewName(NotificationMessage $message, array $options): ?string
    {
        $locale = $message->getLocale() ?? 'ar';
        $type = $message->getType();
        
        return $options['view'] ?? 
               $message->getMetadata()['view'] ?? 
               "mail.notifications.{$type}_{$locale}" ??
               ($locale === 'en' ? 'mail.notification_en' : 'mail.notification_ar');
    }

    /**
     * Prepare content for Email
     */
    protected function prepareContent(NotificationMessage $message, array $options): string
    {
        $content = $message->getContent();
        
        // Convert line breaks to HTML if needed
        if ($options['convert_line_breaks'] ?? true) {
            $content = nl2br($content);
        }

        return $content;
    }
}
