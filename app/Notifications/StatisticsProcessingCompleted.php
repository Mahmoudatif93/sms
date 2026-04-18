<?php

namespace App\Notifications;

use App\Models\StatisticsProcessing;
use App\Models\DashboardNotification;
use App\Events\DashboardNotificationsRefreshRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StatisticsProcessingCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    protected $statisticsProcessing;
    /**
     * Create a new notification instance.
     */
    public function __construct(StatisticsProcessing $statisticsProcessing)
    {
        $this->statisticsProcessing = $statisticsProcessing;
         $this->onQueue('sms-normal');
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        $channels = ['database'];

        if (config('sms.notifications.email', true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        // Set locale based on user's language preference
        $userLocale = $notifiable->lang ?? config('app.locale', 'ar');
        $previousLocale = app()->getLocale();
        app()->setLocale($userLocale);

        $status = $this->statisticsProcessing->status;

        if ($status === StatisticsProcessing::STATUS_COMPLETED) {
            $subject = __('notification.email.statistics.processing.completed.subject');
            $greeting = __('notification.email.statistics.processing.completed.greeting', ['name' => $notifiable->name]);

            $message = (new MailMessage)
                ->subject($subject)
                ->greeting($greeting)
                ->line(__('notification.email.statistics.processing.completed.success_message'))
                ->line(__('notification.email.statistics.processing.completed.processing_id', ['id' => $this->statisticsProcessing->processing_id]))
                ->line(__('notification.email.statistics.processing.completed.total_numbers', ['count' => number_format($this->statisticsProcessing->processed_numbers)]))
                ->line(__('notification.email.statistics.processing.completed.total_cost', ['cost' => number_format($this->statisticsProcessing->total_cost, 2)]))
                ->line(__('notification.email.statistics.processing.completed.review_message'))
                ->action(__('notification.email.statistics.processing.completed.action_button'), url('/sms/statistics/review/' . $this->statisticsProcessing->processing_id))
                ->line(__('notification.email.statistics.processing.completed.thank_you'));
        } else {
            $subject = __('notification.email.statistics.processing.failed.subject');
            $greeting = __('notification.email.statistics.processing.failed.greeting', ['name' => $notifiable->name]);

            $message = (new MailMessage)
                ->subject($subject)
                ->greeting($greeting)
                ->line(__('notification.email.statistics.processing.failed.failure_message'))
                ->line(__('notification.email.statistics.processing.failed.processing_id', ['id' => $this->statisticsProcessing->processing_id]))
                ->line(__('notification.email.statistics.processing.failed.error_message', ['error' => $this->statisticsProcessing->error_message]))
                ->line(__('notification.email.statistics.processing.failed.retry_message'))
                ->line(__('notification.email.statistics.processing.failed.thank_you'));
        }

        // Restore previous locale
        app()->setLocale($previousLocale);

        return $message;
    }

    /**
     * Get the array representation of the notification.
     */

    
    public function toArray($notifiable): array
    {
        // Create dashboard notification
        $this->createDashboardNotification($notifiable);

        return [
            'type' => 'statistics_processing',
            'reviewId' => $this->statisticsProcessing->processing_id,
            'processing_id' => $this->statisticsProcessing->processing_id,
            'status' => $this->statisticsProcessing->status,
            'message' => $this->getNotificationMessage(),
            'data' => [
                'processed_numbers' => $this->statisticsProcessing->processed_numbers,
                'total_cost' => $this->statisticsProcessing->total_cost,
                'completed_at' => $this->statisticsProcessing->completed_at,
                'error_message' => $this->statisticsProcessing->error_message,
            ]
        ];
    }

    /**
     * Create dashboard notification for the user
     */
    private function createDashboardNotification($notifiable): void
    {
        // Get user's current workspace and organization
        $workspace = $notifiable->currentWorkspace();
        if (!$workspace) {
            // If no workspace found, try to get from owned organizations
            $organization = $notifiable->ownedOrganizations()->first();
            if ($organization) {
                $workspace = $organization->workspaces()->first();
            }
        }

        if (!$workspace) {
            \Log::warning('No workspace found for user when creating dashboard notification', [
                'user_id' => $notifiable->id,
                'processing_id' => $this->statisticsProcessing->processing_id
            ]);
            return;
        }

        // Set locale for dashboard notification
        $userLocale = $notifiable->lang ?? config('app.locale', 'ar');
        $previousLocale = app()->getLocale();
        app()->setLocale($userLocale);

        $status = $this->statisticsProcessing->status;

        if ($status === StatisticsProcessing::STATUS_COMPLETED) {
            $title = __('notification.email.statistics.processing.completed.subject');
            $message = __('notification.email.statistics.processing.completed.success_message') . ' ' .
                      __('notification.email.statistics.processing.completed.total_numbers', ['count' => number_format($this->statisticsProcessing->processed_numbers)]) . ' ' .
                      __('notification.email.statistics.processing.completed.total_cost', ['cost' => number_format($this->statisticsProcessing->total_cost, 2)]);
            $icon = 'sms';
            $link = url('/workspaces/' . $workspace->id . '/channels/' . $this->statisticsProcessing->channel_id . '/sms/statistics/review/' . $this->statisticsProcessing->processing_id);
        } else {
            $title = __('notification.email.statistics.processing.failed.subject');
            $message = __('notification.email.statistics.processing.failed.failure_message') . ' ' .
                      __('notification.email.statistics.processing.failed.error_message', ['error' => $this->statisticsProcessing->error_message]);
            $icon = 'sms';
            $link = null;
        }

        // Create dashboard notification
        $dashboardNotification = DashboardNotification::create([
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'icon' => $icon,
            'category' => 'sms-statistics',
            'workspace_id' => $workspace->id,
            'organization_id' => $workspace->organization_id,
            'notifiable_type' => get_class($this->statisticsProcessing),
            'notifiable_id' => $this->statisticsProcessing->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Restore previous locale
        app()->setLocale($previousLocale);

        // Broadcast refresh event
        if ($dashboardNotification) {
            event(new DashboardNotificationsRefreshRequest(
                $workspace->organization_id,
                $workspace->id
            ));
        }
    }

    /**
     * Get the notification message based on status
     */
    private function getNotificationMessage(): string
    {
        switch ($this->statisticsProcessing->status) {
            case StatisticsProcessing::STATUS_COMPLETED:
                return "SMS statistics processing completed successfully. " .
                    number_format($this->statisticsProcessing->processed_numbers) . " numbers processed. " .
                    "Total cost: " . number_format($this->statisticsProcessing->total_cost, 2) . " SAR. " .
                    "Please review and approve to proceed.";

            case StatisticsProcessing::STATUS_FAILED:
                return "SMS statistics processing failed: " . $this->statisticsProcessing->error_message;

            default:
                return "SMS statistics processing status updated to: " . $this->statisticsProcessing->status;
        }
    }
}
