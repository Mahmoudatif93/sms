<?php

namespace App\Jobs;

use App\Models\StatisticsProcessing;
use App\Models\Country;
use App\Services\Sms;
use App\Services\Sms\SmsWalletService;
use App\Services\SendLoginNotificationService;
use App\Models\Setting;
use App\Jobs\AutoApproveStatisticsJob;
use App\Exceptions\InsufficientBalanceException;
use App\Contracts\NotificationManagerInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class ProcessStatisticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $statisticsProcessing;
    protected $user;
    protected $sendNotification;
    protected $notificationManager;
    public $timeout = 3600; // 1 hour timeout
    public $tries = 1;

    public function __construct(StatisticsProcessing $statisticsProcessing, $user)
    {
        $this->statisticsProcessing = $statisticsProcessing;
        $this->user = $user;
        $this->sendNotification = app(SendLoginNotificationService::class);
        $this->notificationManager = app(NotificationManagerInterface::class);
    }

    public function handle(): void
    {
        DB::beginTransaction();
        try {
            Log::info("Starting statistics processing for ID: {$this->statisticsProcessing->processing_id}");

            $this->statisticsProcessing->markAsStarted();

            // معالجة الأرقام وحساب التكلفة
            $result = $this->processNumbersAndCalculateCost();
            $totalCount = $result['totalCount'];
            $totalCost = $result['totalCost'];
            $entries = $result['entries'];
            $numberArr = $result['numberArr'];

            // فحص الرصيد قبل إكمال المعالجة
            $this->checkBalanceBeforeCompletion($totalCost);

            // إكمال المعالجة
            $this->statisticsProcessing->markAsCompleted(
                array_values($entries),
                $numberArr,
                $totalCost,
            );

            // إرسال إشعار النجاح
            $this->sendSuccessNotifications($totalCount, $totalCost);

            DB::commit();

            // جدولة الموافقة التلقائية
            $this->scheduleAutoApproval();

            Log::info("Statistics processing completed for ID: {$this->statisticsProcessing->processing_id}");

        } catch (InsufficientBalanceException $e) {
            DB::rollBack();

            // نسجلها كـ Rejected بدل Failed
            $this->statisticsProcessing->markAsRejected($e->getMessage());

            // إرسال إشعارات الرفض
            $this->sendRejectionNotifications($e->getMessage());

            Log::warning("Statistics processing rejected due to insufficient balance", [
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);

            // لا نرمي الاستثناء → الجوب ينتهي بدون failed()
        } catch (Exception $e) {
            DB::rollBack();

            Log::error("Statistics processing failed for ID: {$this->statisticsProcessing->processing_id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->statisticsProcessing->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    /**
     * معالجة الأرقام وحساب التكلفة الإجمالية
     */
    private function processNumbersAndCalculateCost(): array
    {
        $smsService = app(Sms::class);

        // معالجة الأرقام بشكل صحيح
          $all_numbers = $smsService->processNumbers($this->statisticsProcessing->all_numbers);

        $this->statisticsProcessing->update(['total_numbers' => count($all_numbers)]);

        $entries = [];
        $numberArr = [];
        $processedCount = 0;
        $batchSize = 1000;

        $countries = $this->user
            ? Country::get_active_by_user($this->user->id, $this->user->is_international ?? 0)
            : Country::where('id', 966)->get();

        $batches = array_chunk($all_numbers, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            Log::info("Processing batch " . ($batchIndex + 1) . " of " . count($batches));

            $this->processBatch(
                $batch,
                $entries,
                $numberArr,
                $smsService,
                $countries,
                $processedCount
            );

            $this->statisticsProcessing->updateProgress($processedCount);
            usleep(100000);
        }

        $totalCount = array_reduce($entries, fn($carry, $entry) => $carry + $entry['cnt'], 0);
        $totalCost = array_reduce($entries, fn($carry, $entry) => $carry + $entry['cost'], 0);

        return [
            'totalCount' => $totalCount,
            'totalCost' => $totalCost,
            'entries' => $entries,
            'numberArr' => $numberArr
        ];
    }

    /**
     * فحص الرصيد قبل إكمال المعالجة
     */
    private function checkBalanceBeforeCompletion(float $totalCost): void
    {
        $walletService = app(SmsWalletService::class);
        $workspace = $this->user->workspace ?? $this->statisticsProcessing->workspace;

        $hasSufficientBalance = $walletService->checkBalance($this->user, $workspace, $totalCost);

        if (!$hasSufficientBalance) {
            $userLocale = $this->user->lang ?? config('app.locale', 'ar');
            $errorMessage = $userLocale === 'en'
                ? "Insufficient balance. Required: {$totalCost} points"
                : "الرصيد غير كافي. المطلوب: {$totalCost} نقطة";

            throw new InsufficientBalanceException($errorMessage);
        }
    }

    /**
     * إرسال إشعارات النجاح باستخدام النظام الموحد للإشعارات
     */
    private function sendSuccessNotifications(int $totalCount, float $totalCost): void
    {
        try {
            // إشعار النظام (Laravel Notification) - يتم التحكم به من إعدادات النظام الموحد
            if (config('notifications.features.database_notifications', true)) {
                $this->user->notify(new \App\Notifications\StatisticsProcessingCompleted($this->statisticsProcessing));
            }

            // إرسال إشعار النجاح باستخدام النظام الموحد
            $this->sendProcessingCompletedNotificationUnified($totalCount, $totalCost);

        } catch (Exception $e) {
            Log::error('Error sending success notifications', [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);

            // Fallback to old system if unified system fails
            $this->sendProcessingCompletedNotification($totalCount, $totalCost);
        }
    }

    /**
     * إرسال إشعارات الرفض بسبب عدم كفاية الرصيد
     */
    private function sendRejectionNotifications(string $errorMessage): void
    {
        // إشعار النظام
        if (config('sms.notifications.enabled', true)) {
            $this->user->notify(new \App\Notifications\StatisticsProcessingCompleted($this->statisticsProcessing));
        }

        // إشعار SMS للمستخدم بالرفض
        $this->sendInsufficientBalanceNotification($errorMessage);
    }

    protected function processBatch(
        array $batch,
        array &$entries,
        array &$numberArr,
        Sms $smsService,
        $countries,
        int &$processedCount
    ): void {
        foreach ($batch as $number) {
            try {
                $prefix = $smsService->getNumberPrefix($number, $this->statisticsProcessing->sms_type);

                if ($smsService->hasNumberProcessor($prefix)) {
                    $numberToProcess = $number;

                    if ($prefix === "E" || $this->statisticsProcessing->sms_type === "VARIABLES") {
                        $numberToProcess = $this->statisticsProcessing->excel_file;
                    }

                    $processor = $smsService->getNumberProcessor($prefix);
                    $processor->process(
                        $numberToProcess,
                        $entries,
                        $this->statisticsProcessing->message_length,
                        $numberArr,
                        $this->statisticsProcessing->message,
                        $countries
                    );
                }

                $processedCount++;
            } catch (Exception $e) {
                Log::warning("Failed to process number: $number", [
                    'error' => $e->getMessage(),
                    'processing_id' => $this->statisticsProcessing->processing_id
                ]);
                $processedCount++;
            }
        }
    }

    public function failed(Exception $exception): void
    {
        Log::error("ProcessStatisticsJob failed permanently", [
            'processing_id' => $this->statisticsProcessing->processing_id,
            'error' => $exception->getMessage()
        ]);

        $this->statisticsProcessing->markAsFailed($exception->getMessage());

        // إرسال إشعارات الفشل
        if (config('sms.notifications.enabled', true)) {
            $this->user->notify(new \App\Notifications\StatisticsProcessingCompleted($this->statisticsProcessing));
        }

        $this->sendFailureNotification($exception->getMessage());
    }

    /**
     * إرسال إشعار النجاح باستخدام النظام الموحد للإشعارات
     */
    private function sendProcessingCompletedNotificationUnified(int $totalCount, float $totalCost): void
    {
        try {
            $userLocale = $this->user->lang ?? config('app.locale', 'ar');
            $siteName = Setting::get_by_name('site_name') ?? 'Dreams';
            $processingTime = now()->format('Y-m-d H:i:s');

            // تحضير المستقبلين
            $recipients = [['type' => 'user', 'identifier' => $this->user->id]];

            // الحصول على القنوات المتاحة من النظام الموحد
            $availableChannels = [];

            // فحص قناة SMS
            if (config('notifications.available_channels.sms.enabled', true) && !empty($this->user->number)) {
                $availableChannels[] = 'sms';
            }

            // فحص قناة البريد الإلكتروني
            if (config('notifications.available_channels.email.enabled', true) && !empty($this->user->email)) {
                $availableChannels[] = 'email';
            }

            // فحص قناة Telegram إذا كانت متاحة
            if (config('notifications.available_channels.telegram.enabled', false)) {
                $availableChannels[] = 'telegram';
            }

            // إذا لم تكن هناك قنوات متاحة، استخدم النظام القديم
            if (empty($availableChannels)) {
                Log::warning('No notification channels available for user', [
                    'user_id' => $this->user->id,
                    'processing_id' => $this->statisticsProcessing->processing_id
                ]);
                return;
            }

            // متغيرات القالب
            $templateVariables = [
                'user_name' => $this->user->name ?? $this->user->username,
                'site_name' => $siteName,
                'total_count' => number_format($totalCount),
                'total_cost' => number_format($totalCost, 2),
                'processing_id' => $this->statisticsProcessing->processing_id,
                'processing_time' => $processingTime,
                'auto_approval_notice' => __('notification.sms.statistics.processing.auto_approval_notice', [], $userLocale)
            ];

            // خيارات الإرسال
            $options = [
                'sender_name' => Setting::get_by_name('system_sms_sender') ?? 'Dreams',
                'sender_type' => 'admin',
                'priority' => 'high',
                'locale' => $userLocale
            ];

            // إرسال الإشعار باستخدام النظام الموحد
            $result = $this->notificationManager->sendFromTemplate(
                'statistics_processing_success',
                $recipients,
                $templateVariables,
                $availableChannels,
                $options
            );

            if ($result['success']) {
                Log::info('Statistics processing success notification sent successfully', [
                    'user_id' => $this->user->id,
                    'processing_id' => $this->statisticsProcessing->processing_id,
                    'channels' => $availableChannels,
                    'total_count' => $totalCount,
                    'total_cost' => $totalCost,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                Log::warning('Failed to send statistics processing success notification', [
                    'user_id' => $this->user->id,
                    'processing_id' => $this->statisticsProcessing->processing_id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                // Fallback to old system
                throw new Exception($result['error'] ?? 'Failed to send notification via unified system');
            }

        } catch (Exception $e) {
            Log::error('Error sending statistics processing success notification via unified system', [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * إرسال إشعار انتهاء المعالجة وانتظار الإجراءات (النظام القديم)
     */
    private function sendProcessingCompletedNotification(int $totalCount, float $totalCost): void
    {
        try {
            // التحقق من إعدادات SMS في النظام القديم (للتوافق مع النظام القديم)
            if (!config('notifications.available_channels.sms.enabled', true)) {
                return;
            }

            $systemSmsSender = Setting::get_by_name('system_sms_sender') ?? 'DREAMS';
            $userLocale = $this->user->lang ?? config('app.locale', 'ar');
            $previousLocale = app()->getLocale();
            app()->setLocale($userLocale);

            $message = __('notification.sms.statistics.processing.success', [
                'count' => number_format($totalCount),
                'cost' => number_format($totalCost, 2)
            ]);
            $message .= ' ' . __('notification.sms.statistics.processing.auto_approval_notice');

            app()->setLocale($previousLocale);

            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                $this->user->number,
                $message,
                'admin',
                $this->user->id
            );

            Log::info("Processing completed SMS notification sent to user", [
                'user_id' => $this->user->id,
                'user_number' => $this->user->number,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'total_count' => $totalCount,
                'total_cost' => $totalCost,
                'message_length' => mb_strlen($message, 'UTF-8'),
                'user_locale' => $userLocale
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send processing completed SMS notification", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * إرسال إشعار عدم كفاية الرصيد
     */
    private function sendInsufficientBalanceNotification(string $errorMessage): void
    {
        try {
            if (!config('sms.notifications.sms', true)) {
                return;
            }

            $systemSmsSender = Setting::get_by_name('system_sms_sender') ?? 'DREAMS';
            $userLocale = $this->user->lang ?? config('app.locale', 'ar');
            $previousLocale = app()->getLocale();
            app()->setLocale($userLocale);

            $message = __('notification.sms.statistics.processing.insufficient_balance', [
                'error' => substr($errorMessage, 0, 50)
            ]);

            app()->setLocale($previousLocale);

            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                $this->user->number,
                $message,
                'admin',
                $this->user->id
            );

            Log::info("Insufficient balance SMS notification sent to user", [
                'user_id' => $this->user->id,
                'user_number' => $this->user->number,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error_message' => $errorMessage,
                'message_length' => mb_strlen($message, 'UTF-8'),
                'user_locale' => $userLocale
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send insufficient balance SMS notification", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * إرسال إشعار فشل عام (للاستخدام في failed method)
     */
    private function sendFailureNotification(string $errorMessage): void
    {
        try {
            if (!config('sms.notifications.sms', true)) {
                return;
            }

            $systemSmsSender = Setting::get_by_name('system_sms_sender') ?? 'DREAMS';
            $userLocale = $this->user->lang ?? config('app.locale', 'ar');
            $previousLocale = app()->getLocale();
            app()->setLocale($userLocale);

            $message = __('notification.sms.statistics.processing.failure', [
                'error' => substr($errorMessage, 0, 30)
            ]);

            app()->setLocale($previousLocale);

            $this->sendNotification->sendSmsNotification(
                $systemSmsSender,
                $this->user->number,
                $message,
                'admin',
                $this->user->id
            );

            Log::info("Failure SMS notification sent to user", [
                'user_id' => $this->user->id,
                'user_number' => $this->user->number,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error_message' => $errorMessage,
                'message_length' => mb_strlen($message, 'UTF-8'),
                'user_locale' => $userLocale
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to send failure SMS notification", [
                'user_id' => $this->user->id,
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function scheduleAutoApproval(): void
    {
        try {
            $workspace = $this->user->workspace ?? null;

            AutoApproveStatisticsJob::dispatch(
                $this->statisticsProcessing,
                $this->user,
                $workspace
            )->delay(now()->addMinutes(10))
                ->onQueue('sms-normal');

            Log::info("Auto-approval job scheduled", [
                'processing_id' => $this->statisticsProcessing->processing_id,
                'scheduled_for' => now()->addMinutes(10)->toDateTimeString()
            ]);

        } catch (Exception $e) {
            Log::warning("Failed to schedule auto-approval job", [
                'processing_id' => $this->statisticsProcessing->processing_id,
                'error' => $e->getMessage()
            ]);
        }
    }


}
