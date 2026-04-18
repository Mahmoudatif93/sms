<?php

namespace App\Actions\Sms;

use App\Models\User;
use App\Models\Message;
use App\Services\Sms\SmsDataProcessor;
use App\Services\Sms\SmsValidationService;
use App\Services\Sms\SmsWalletService;
use App\Services\Sms\SmsProcessingService;
use App\Services\FileUploadService;
use App\Helpers\Sms\MessageHelper;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;
use App\Exceptions\UserInactiveException;
use Illuminate\Validation\ValidationException;

class SendSmsAction
{
    public function __construct(
        private ProcessSmsStatisticsAction $statisticsAction,
        private SmsDataProcessor $dataProcessor,
        private SmsValidationService $validationService,
        private SmsWalletService $walletService,
        private SmsProcessingService $processingService,
        private FileUploadService $fileUploadService
    ) {}


    /**
     * Execute SMS sending process
     */
    public function execute(array $data, User $user): Message
    {
        try {
            // Process and validate data
            $processedData = $this->dataProcessor->processMessageData($data, $user);
            $this->validationService->validateForSending($processedData, $user);

            // Handle empty sender for test mode
            $processedData = $this->dataProcessor->handleEmptySender($processedData, $user);

            // Process SMS statistics
            $statisticsResult = $this->processingService->processRequest($processedData, $user);

            // Merge statistics results
            $processedData = array_merge($processedData, $statisticsResult);

            // Validate message count
            $this->dataProcessor->validateMessageCount($processedData['count']);

            // Validate and deduct balance
            $this->walletService->validateAndDeductBalance(
                $user,
                $data['workspace'],
                $processedData['cost'],
                "Send SMS Campaign"
            );

            // Determine if message needs review
            $isReviewMessage = MessageHelper::isReviewMessage(
                $processedData['message'],
                $processedData['from'] ?? $processedData['sender_name'],
                $user->isAllowUrl(),
                $user->isAllowSendBlock(),
                $processedData['count']
            );

            // Create and process message
            $message = $this->processingService->createMessage($processedData, $user, $isReviewMessage);

            // Clean up uploaded files
            $this->cleanupFiles($processedData);

            return $message;
        } catch (InsufficientBalanceException $e) {
            $this->cleanupFiles($processedData ?? $data);
            throw $e;
        } catch (WalletNotFoundException $e) {
            $this->cleanupFiles($processedData ?? $data);
            throw $e;
        } catch (ValidationException $e) {
            $this->cleanupFiles($processedData ?? $data);
            throw $e;
        } catch (\Exception $e) {
            $this->cleanupFiles($processedData ?? $data);

            \Log::error('SMS sending failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new \Exception('Failed to send SMS: ' . $e->getMessage());
        }
    }

    /**
     * Execute SMS sending from StatisticsProcessing (for background jobs)
     */
    public function executeFromStatisticsProcessing(\App\Models\StatisticsProcessing $statisticsProcessing, User $user): Message
    {
        try {
            // Validate user is active
            $this->validationService->validateUserActive($user);

            // Validate user owns the statistics
            if ($statisticsProcessing->user_id !== $user->id) {
                throw new \UnauthorizedHttpException('', 'Unauthorized access to statistics processing');
            }

            // Validate statistics is approved
            if (!$statisticsProcessing->isApproved()) {
                throw new \Exception('Statistics processing is not approved for sending');
            }

            // Convert StatisticsProcessing to processable data
            $processedData = $this->convertStatisticsProcessingToData($statisticsProcessing);

            // Handle empty sender for test mode
            $processedData = $this->dataProcessor->handleEmptySender($processedData, $user);

            // Validate message content
            $this->validationService->validateMessageContent($processedData['message']);

            // Validate and deduct balance
            $this->walletService->validateAndDeductBalance(
                $user,
                $statisticsProcessing->workspace,
                $statisticsProcessing->total_cost,
                "Send SMS Campaign from Approved Statistics"
            );

            // Determine if message needs review
            $isReviewMessage = MessageHelper::isReviewMessage(
                $processedData['message'],
                $processedData['sender_name'],
                $user->isAllowUrl(),
                $user->isAllowSendBlock(),
                $processedData['count']
            );
            // Create and process message
            $message = $this->processingService->createMessage($processedData, $user, $isReviewMessage);

            // Clean up
            $this->fileUploadService->deleteFileOss($statisticsProcessing->excel_file);

            return $message;
        } catch (\Exception $e) {
            // Clean up on error
            $this->fileUploadService->deleteFileOss($statisticsProcessing->excel_file ?? '');

            \Log::error('SMS sending from StatisticsProcessing failed', [
                'user_id' => $user->id,
                'processing_id' => $statisticsProcessing->processing_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Convert StatisticsProcessing to processable data format
     */
    private function convertStatisticsProcessingToData(\App\Models\StatisticsProcessing $statisticsProcessing): array
    {
        return [
            'workspace_id' => $statisticsProcessing->workspace_id,
            'message' => $statisticsProcessing->message,
            'count' => $statisticsProcessing->processed_numbers,
            'cost' => $statisticsProcessing->total_cost,
            'leng' => $statisticsProcessing->message_length,
            'send_time' => $statisticsProcessing->send_time,
            'sms_type' => $statisticsProcessing->sms_type,
            'from' => $statisticsProcessing->sender_name,
            'sender_name' => $statisticsProcessing->sender_name,
            'excle_file' => $statisticsProcessing->excel_file,
            'all_numbers_json' => json_encode($statisticsProcessing->all_numbers_json),
            'send_time_method' => $statisticsProcessing->send_time_method,
            'repeation_times' => $statisticsProcessing->repeation_times,
        ];
    }

    /**
     * Clean up uploaded files on error
     */
    private function cleanupFiles(array $data): void
    {
        if (isset($data['excle_file'])) {
            $this->fileUploadService->deleteFileOss($data['excle_file']);
        }
    }
}
