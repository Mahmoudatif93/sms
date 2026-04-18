<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationManagerInterface;
use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Organization;
use App\Models\NotificationLog;
use App\Services\Notifications\ChannelManager;
use App\Services\Notifications\PreferenceManager;
use App\Services\Notifications\TemplateManager;
use App\Jobs\Notifications\ProcessNotificationJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Carbon\Carbon;
use Exception;

class NotificationManager implements NotificationManagerInterface
{
    protected ChannelManager $channelManager;
    protected PreferenceManager $preferenceManager;
    protected TemplateManager $templateManager;

    public function __construct(
        ChannelManager $channelManager,
        PreferenceManager $preferenceManager,
        TemplateManager $templateManager
    ) {
        $this->channelManager = $channelManager;
        $this->preferenceManager = $preferenceManager;
        $this->templateManager = $templateManager;
    }

    /**
     * Send a notification through specified channels
     */
    public function send(NotificationMessage $message): array
    {
        try {
            // Log the notification attempt
            $notificationLog = $this->createNotificationLog($message);

            // Check if message should be queued
            if ($this->shouldQueue($message)) {
                return $this->queueNotification($message, $notificationLog);
            }

            // Send immediately
            return $this->sendImmediately($message, $notificationLog);

        } catch (Exception $e) {
            Log::error("Notification send failed", [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => $message->getId()
            ];
        }
    }

    /**
     * Send notification to a user with their preferences
     */
    public function sendToUser(User $user, string $type, string $content, array $data = []): array
    {
        $message = new NotificationMessage($type, $content);
        $message->setData($data)
               ->setSender($user)
               ->setLocale($user->lang ?? 'ar');

        // Add user as recipient
        $message->addRecipient('user', $user->id);

        // Get user's effective preferences
        $preferences = $this->preferenceManager->getEffectivePreferences($user, $type);
        
        // Filter channels based on preferences
        $enabledChannels = [];
        foreach ($preferences as $channel => $preference) {
            if ($preference->enabled && $preference->isAllowedNow()) {
                $enabledChannels[] = $channel;
            }
        }

        if (empty($enabledChannels)) {
            Log::info("No enabled channels for user notification", [
                'user_id' => $user->id,
                'type' => $type
            ]);
            
            return [
                'success' => false,
                'error' => 'No enabled channels for user',
                'message_id' => $message->getId()
            ];
        }

        // Set channels
        foreach ($enabledChannels as $channel) {
            $message->addChannel($channel);
        }

        return $this->send($message);
    }

    /**
     * Send notification to a workspace
     */
    public function sendToWorkspace(Workspace $workspace, string $type, string $content, array $data = []): array
    {
        $message = new NotificationMessage($type, $content);
        $message->setData($data)
               ->setWorkspace($workspace);

        // Get workspace users
        $users = $workspace->users()->get();
        
        foreach ($users as $user) {
            $message->addRecipient('user', $user->id);
        }

        // Use workspace default channels or system defaults
        $channels = $this->channelManager->getDefaultChannelsForType($type);
        foreach ($channels as $channel) {
            $message->addChannel($channel);
        }

        return $this->send($message);
    }

    /**
     * Send notification to an organization
     */
    public function sendToOrganization(Organization $organization, string $type, string $content, array $data = []): array
    {
        $message = new NotificationMessage($type, $content);
        $message->setData($data)
               ->setOrganization($organization);

        // Get organization users
        $users = $organization->users()->get();
        
        foreach ($users as $user) {
            $message->addRecipient('user', $user->id);
        }

        // Use organization default channels or system defaults
        $channels = $this->channelManager->getDefaultChannelsForType($type);
        foreach ($channels as $channel) {
            $message->addChannel($channel);
        }

        return $this->send($message);
    }

    /**
     * Send notification using a template
     */
    public function sendFromTemplate(string $templateId, array $recipients, array $variables = [], array $channels = [], array $options = []): array
    {
        try {
            $message = $this->templateManager->createFromTemplate(
                $templateId,
                $variables,
                $recipients,
                $channels,
                $variables['locale'] ?? 'ar'
            );
            // إضافة معلومات المرسل إذا كانت متوفرة
            if (isset($options['sender_name'])) {
                $message->setData(array_merge($message->getData(), [
                    'sender_name' => $options['sender_name']
                ]));
            }

            if (isset($options['sender_type'])) {
                $message->setData(array_merge($message->getData(), [
                    'sender_type' => $options['sender_type'] // 'admin' أو 'user'
                ]));
            }

            // إضافة أي خيارات إضافية إلى metadata
            if (!empty($options)) {
                $currentMetadata = $message->getMetadata();
                $message->setMetadata(array_merge($currentMetadata, [
                    'options' => $options
                ]));
            }
            return $this->send($message);

        } catch (Exception $e) {
            Log::error("Template notification failed", [
                'template_id' => $templateId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'template_id' => $templateId
            ];
        }
    }

    /**
     * Schedule a notification for later delivery
     */
    public function schedule(NotificationMessage $message, Carbon $scheduledAt): string
    {
        $message->setScheduledAt($scheduledAt);
        
        $notificationLog = $this->createNotificationLog($message, NotificationLog::STATUS_SCHEDULED);
        
        // Queue the notification with delay
        $delay = $scheduledAt->diffInSeconds(now());
        ProcessNotificationJob::dispatch($message, $notificationLog->id)
            ->delay($delay)->onQueue('sms-high');
            // ->onQueue(config('notifications.queue.queue_name', 'notifications'));

        Log::info("Notification scheduled", [
            'message_id' => $message->getId(),
            'scheduled_at' => $scheduledAt->toISOString(),
            'delay_seconds' => $delay
        ]);

        return $notificationLog->id;
    }

    /**
     * Cancel a scheduled notification
     */
    public function cancelScheduled(string $notificationId): bool
    {
        try {
            $log = NotificationLog::find($notificationId);
            
            if (!$log || $log->status !== NotificationLog::STATUS_SCHEDULED) {
                return false;
            }

            $log->update(['status' => NotificationLog::STATUS_CANCELLED]);
            
            Log::info("Scheduled notification cancelled", [
                'notification_id' => $notificationId
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to cancel scheduled notification", [
                'notification_id' => $notificationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get notification status
     */
    public function getStatus(string $notificationId): ?array
    {
        $log = NotificationLog::find($notificationId);
        
        if (!$log) {
            return null;
        }

        return [
            'id' => $log->id,
            'notification_id' => $log->notification_id,
            'status' => $log->status,
            'channel' => $log->channel,
            'recipient' => $log->recipient_identifier,
            'sent_at' => $log->sent_at?->toISOString(),
            'delivered_at' => $log->delivered_at?->toISOString(),
            'error_message' => $log->error_message,
            'retry_count' => $log->retry_count,
            'next_retry_at' => $log->next_retry_at?->toISOString(),
        ];
    }

    /**
     * Register a notification channel
     */
    public function registerChannel(string $name, \App\Contracts\NotificationChannelInterface $channel): self
    {
        $this->channelManager->registerChannel($name, $channel);
        return $this;
    }

    /**
     * Get available channels
     */
    public function getAvailableChannels(): array
    {
        return $this->channelManager->getAvailableChannels();
    }

    /**
     * Set default channels for a notification type
     */
    public function setDefaultChannels(string $type, array $channels): self
    {
        $this->channelManager->setDefaultChannels($type, $channels);
        return $this;
    }

    /**
     * Get user's notification preferences
     */
    public function getUserPreferences(User $user, string $type): array
    {
        return $this->preferenceManager->getUserPreferences($user, $type);
    }

    /**
     * Update user's notification preferences
     */
    public function updateUserPreferences(User $user, string $type, array $preferences): bool
    {
        return $this->preferenceManager->setUserPreferences($user, $type, $preferences);
    }

    /**
     * Send notification immediately
     */
    protected function sendImmediately(NotificationMessage $message, NotificationLog $notificationLog): array
    {
        $results = $this->channelManager->sendViaMultipleChannels($message);
        
        // Update notification log based on results
        $this->updateNotificationLogFromResults($notificationLog, $results);
        
        return [
            'success' => !empty(array_filter($results, fn($r) => $r->isSuccess())),
            'results' => $results,
            'message_id' => $message->getId(),
            'notification_log_id' => $notificationLog->id
        ];
    }

    /**
     * Queue notification for background processing
     */
    protected function queueNotification(NotificationMessage $message, NotificationLog $notificationLog): array
    {
        $notificationLog->update(['status' => NotificationLog::STATUS_QUEUED]);
        ProcessNotificationJob::dispatch($message, $notificationLog->id)->onQueue('sms-high');
            // ->onQueue(config('notifications.queue.queue_name', 'notifications'));

        return [
            'success' => true,
            'queued' => true,
            'message_id' => $message->getId(),
            'notification_log_id' => $notificationLog->id
        ];
    }

    /**
     * Check if notification should be queued
     */
    protected function shouldQueue(NotificationMessage $message): bool
    {
        return config('notifications.queue.enabled', false) ||
               $message->isScheduled() ||
               count($message->getRecipients()) > config('notifications.queue_threshold', 10);
    }

    /**
     * Create notification log entry
     */
    protected function createNotificationLog(NotificationMessage $message, string $status = NotificationLog::STATUS_PENDING): NotificationLog
    {
        return NotificationLog::create([
            'notification_id' => $message->getId(),
            'type' => $message->getType(),
            'channel' => implode(',', array_keys($message->getChannels())),
            'title' => $message->getTitle(),
            'content' => $message->getContent(),
            'data' => $message->getData(),
            'status' => $status,
            'user_id' => $message->getSender()?->id,
            'workspace_id' => $message->getWorkspace()?->id,
            'organization_id' => $message->getOrganization()?->id,
            'template_id' => $message->getTemplateId(),
            'template_variables' => $message->getTemplateVariables(),
            'priority' => $message->getPriority(),
            'metadata' => $message->getMetadata(),
            'scheduled_at' => $message->getScheduledAt(),
            'recipient_type' => 'multiple',
            'recipient_id' => null,
            'recipient_identifier' => count($message->getRecipients()) . ' recipients',
        ]);
    }

    /**
     * Update notification log from channel results
     */
    protected function updateNotificationLogFromResults(NotificationLog $notificationLog, array $results): void
    {
        $successCount = count(array_filter($results, fn($r) => $r->isSuccess()));
        $totalCount = count($results);
        
        if ($successCount === $totalCount) {
            $notificationLog->markAsSent();
        } elseif ($successCount > 0) {
            $notificationLog->update([
                'status' => NotificationLog::STATUS_SENT,
                'error_message' => "Partial success: {$successCount}/{$totalCount}",
                'sent_at' => now()
            ]);
        } else {
            $errors = array_map(fn($r) => $r->getError(), array_filter($results, fn($r) => $r->isFailure()));
            $notificationLog->markAsFailed(implode('; ', $errors));
        }
    }
}
