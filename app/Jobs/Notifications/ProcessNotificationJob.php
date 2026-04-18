<?php

namespace App\Jobs\Notifications;

use App\Notifications\Core\NotificationMessage;
use App\Models\NotificationLog;
use App\Services\Notifications\NotificationManager;
use App\Services\Notifications\ChannelManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected NotificationMessage $message;
    protected string $notificationLogId;
    
    public $timeout = 300; // 5 minutes
    public $tries = 3;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(NotificationMessage $message, string $notificationLogId)
    {
        $this->message = $message;
        $this->notificationLogId = $notificationLogId;
        
        // Set queue based on priority
        // $this->onQueue($this->getQueueName($message->getPriority()));
    }

    /**
     * Execute the job.
     */
    public function handle(ChannelManager $channelManager): void
    {
        try {
            Log::info("Processing notification job", [
                'message_id' => $this->message->getId(),
                'notification_log_id' => $this->notificationLogId,
                 'attempt' => $this->attempts()
            ]);


            $notificationLog = NotificationLog::find($this->notificationLogId);
            
            if (!$notificationLog) {
                Log::error("Notification log not found", [
                    'notification_log_id' => $this->notificationLogId
                ]);
                return;
            }

            // Check if notification was cancelled
            if ($notificationLog->status === NotificationLog::STATUS_CANCELLED) {
                Log::error("Notification was cancelled", [
                    'notification_log_id' => $this->notificationLogId
                ]);
                return;
            }

            // Update status to sending
            $notificationLog->update(['status' => NotificationLog::STATUS_SENDING]);
           
            // Send via channels
            $results = $channelManager->sendViaMultipleChannels($this->message);
            
            // Update notification log based on results
            $this->updateNotificationLogFromResults($notificationLog, $results);
            
            // Log individual channel results
            foreach ($results as $channel => $result) {
                $this->createChannelLog($notificationLog, $channel, $result);
            }

            Log::info("Notification job completed", [
                'message_id' => $this->message->getId(),
                'notification_log_id' => $this->notificationLogId,
                'results' => array_map(fn($r) => $r->isSuccess(), $results)
            ]);

        } catch (Exception $e) {
            Log::error("Notification job failed", [
                'message_id' => $this->message->getId(),
                'notification_log_id' => $this->notificationLogId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update notification log
            $notificationLog = NotificationLog::find($this->notificationLogId);
            if ($notificationLog) {
                $canRetry = $this->attempts() < $this->tries;
                $notificationLog->markAsFailed($e->getMessage(), $canRetry);
            }

            throw $e;
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("Notification job failed permanently", [
            'message_id' => $this->message->getId(),
            'notification_log_id' => $this->notificationLogId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);

        $notificationLog = NotificationLog::find($this->notificationLogId);
        if ($notificationLog) {
            $notificationLog->markAsFailed(
                "Job failed after {$this->tries} attempts: " . $exception->getMessage(),
                false
            );
        }
    }

    /**
     * Get queue name based on priority
     */
    protected function getQueueName(string $priority): string
    {
        $queueMapping = [
            'urgent' => 'notifications-urgent',
            'high' => 'notifications-high',
            'normal' => 'notifications',
            'low' => 'notifications-low',
        ];

        return $queueMapping[$priority] ?? 'notifications';
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

    /**
     * Create individual channel log entries
     */
    protected function createChannelLog(NotificationLog $parentLog, string $channel, $result): void
    {
        try {
            // Create individual logs for each recipient/channel combination
            foreach ($this->message->getRecipients() as $recipient) {
                NotificationLog::create([
                    'notification_id' => $this->message->getId(),
                    'type' => $this->message->getType(),
                    'channel' => $channel,
                    'recipient_type' => $recipient['type'],
                    'recipient_id' => $this->getRecipientId($recipient),
                    'recipient_identifier' => $recipient['identifier'],
                    'title' => $this->message->getTitle(),
                    'content' => $this->message->getContent(),
                    'data' => $this->message->getData(),
                    'status' => $result->isSuccess() ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
                    'external_id' => $result->getExternalId(),
                    'error_message' => $result->getError(),
                    'sent_at' => $result->isSuccess() ? now() : null,
                    'user_id' => $this->message->getSender()?->id,
                    'workspace_id' => $this->message->getWorkspace()?->id,
                    'organization_id' => $this->message->getOrganization()?->id,
                    'template_id' => $this->message->getTemplateId(),
                    'template_variables' => $this->message->getTemplateVariables(),
                    'priority' => $this->message->getPriority(),
                    'metadata' => array_merge($this->message->getMetadata(), $result->getMetadata()),
                ]);
            }
        } catch (Exception $e) {
            Log::warning("Failed to create channel log", [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get recipient ID from recipient data
     */
    protected function getRecipientId(array $recipient): ?int
    {
        if ($recipient['type'] === 'user') {
            return (int) $recipient['identifier'];
        }

        return null;
    }

    /**
     * Calculate delay for retry
     */
    public function retryAfter(): int
    {
        // Exponential backoff: 1 minute, 5 minutes, 15 minutes
        $delays = [60, 300, 900];
        $attempt = $this->attempts() - 1;
        
        return $delays[$attempt] ?? 900;
    }

    /**
     * Determine if the job should be retried
     */
    public function shouldRetry(Exception $exception): bool
    {
        // Don't retry for certain types of errors
        $nonRetryableErrors = [
            'Template not found',
            'Invalid recipient',
            'Channel not supported',
        ];

        foreach ($nonRetryableErrors as $error) {
            if (str_contains($exception->getMessage(), $error)) {
                return false;
            }
        }

        return $this->attempts() < $this->tries;
    }
}
