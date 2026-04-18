<?php

namespace App\Jobs;

use App\Models\StatisticsProcessing;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Actions\Sms\SendSmsAction;
use App\Services\SendLoginNotificationService;
use App\Models\Setting;
use App\Exceptions\UserInactiveException;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ProcessApprovedSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $statisticsProcessing;
    protected $user;
    protected $workspace;
    protected $sendSmsAction;
    protected $sendNotification;
    public $timeout = 3600; // 1 hour timeout
    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(StatisticsProcessing $statisticsProcessing, User $user, $workspace = null)
    {
        $this->statisticsProcessing = $statisticsProcessing;
        $this->user = $user;
        $this->workspace = $workspace;
        $this->sendSmsAction = app(SendSmsAction::class);
        $this->sendNotification = app(SendLoginNotificationService::class);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting approved sending process for ID: {$this->statisticsProcessing->processing_id}");

            // Validate that the processing is approved
            if (!$this->statisticsProcessing->isApproved()) {
                throw new Exception("Statistics processing is not approved for sending");
            }

            // Use SendSmsAction to handle the sending (same logic as send() endpoint)
            $message = $this->sendSmsAction->executeFromStatisticsProcessing(
                $this->statisticsProcessing,
                $this->user
            );

            // Send success notification
            $this->sendSuccessNotification($message);

            Log::info("Approved sending process completed for ID: {$this->statisticsProcessing->processing_id}", [
                'message_id' => $message->id
            ]);

        } catch (UserInactiveException $e) {
            Log::error("User inactive during approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
            $this->sendFailureNotification($e->getMessage());
            throw $e;

        } catch (InsufficientBalanceException $e) {
            Log::error("Insufficient balance during approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'cost' => $this->statisticsProcessing->total_cost,
                'error' => $e->getMessage()
            ]);
            $this->sendFailureNotification($e->getMessage());
            throw $e;

        } catch (WalletNotFoundException $e) {
            Log::error("Wallet not found during approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
            $this->sendFailureNotification($e->getMessage());
            throw $e;

        } catch (Exception $e) {
            Log::error("Approved sending process failed for ID: {$this->statisticsProcessing->processing_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->sendFailureNotification($e->getMessage());
            throw $e;
        }
    }



    /**
     * Send success notification to user
     */
    private function sendSuccessNotification(Message $message): void
    {
        try {
            if (!config('sms.notifications.sms', true)) {
                return;
            }

            $systemSmsSender = Setting::get_by_name('system_sms_sender') ?? 'DREAMS';

            $template = config('sms.notification_messages.sending_success');
            $notificationMessage = str_replace([
                '{count}',
                '{processing_id}'
            ], [
                number_format($this->statisticsProcessing->processed_numbers),
                $this->statisticsProcessing->processing_id
            ], $template);

            // Ensure message doesn't exceed SMS length limit
            $maxLength = config('sms.notification_messages.max_message_length', 160);
            if (strlen($notificationMessage) > $maxLength) {
                $notificationMessage = substr($notificationMessage, 0, $maxLength - 3) . '...';
            }

            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                $this->user->number,
                $notificationMessage,
                'admin',
                $this->user->id
            );

            Log::info("Success notification sent for approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'message_id' => $message->id
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send success notification for approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send failure notification to user
     */
    private function sendFailureNotification(string $errorMessage): void
    {
        try {
            if (!config('sms.notifications.sms', true)) {
                return;
            }

            $systemSmsSender = Setting::get_by_name('system_sms_sender') ?? 'DREAMS';

            $template = config('sms.notification_messages.sending_failure');
            
            // Convert message components to UTF-8 and ensure proper encoding
            $processingId = mb_convert_encoding($this->statisticsProcessing->processing_id, 'UTF-8', 'UTF-8');
            $error = mb_convert_encoding(substr($errorMessage, 0, 50), 'UTF-8', 'UTF-8');
            
            $message = str_replace([
                '{processing_id}',
                '{error}'
            ], [
                $processingId,
                $error
            ], $template);

            // Ensure message is properly encoded in UTF-8
            $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');

            // Ensure message doesn't exceed SMS length limit
            $maxLength = config('sms.notification_messages.max_message_length', 160);
            if (mb_strlen($message, 'UTF-8') > $maxLength) {
                $message = mb_substr($message, 0, $maxLength - 3, 'UTF-8') . '...';
            }

            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                $this->user->number,
                $message,
                'admin',
                $this->user->id
            );

        } catch (Exception $e) {
            Log::warning("Failed to send failure notification for approved sending", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(Exception $exception): void
    {
        Log::error("ProcessApprovedSendingJob failed permanently", [
            'processing_id' => $this->statisticsProcessing->processing_id,
            'error' => $exception->getMessage()
        ]);

        // $this->sendFailureNotification("فشل نهائي بعد {$this->tries} محاولات");
    }
}
