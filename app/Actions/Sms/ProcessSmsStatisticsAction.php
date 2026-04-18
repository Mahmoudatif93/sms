<?php

namespace App\Actions\Sms;

use App\Models\User;
use App\Models\MessageStatistic;
use App\Services\Sms\SmsDataProcessor;
use App\Services\Sms\SmsValidationService;
use App\Services\Sms\SmsProcessingService;
use App\Helpers\Sms\MessageHelper;
use Illuminate\Validation\ValidationException;

class ProcessSmsStatisticsAction
{
    public function __construct(
        private SmsDataProcessor $dataProcessor,
        private SmsValidationService $validationService,
        private SmsProcessingService $processingService
    ) {}

    /**
     * Process SMS statistics with background support
     */
    public function execute(array $data, User $user): array
    {
        try {
            // Process and validate data
            $processedData = $this->dataProcessor->processStatisticsData($data, $user);
            $this->validationService->validateForStatistics($processedData, $user);

            // Check if this requires background processing
            $estimatedCount = $this->processingService->estimateNumberCount(
                $data['all_numbers'],
                $data['sms_type'],
                $data['excle_file'] ?? null,
                null
            );

            $backgroundThreshold = config('sms.background_processing_threshold', 10000);
            $backgroundVariableThreshold = config('sms.background_varaiable_processing_threshold', 5000);
            if (($data['sms_type'] === 'VARIABLES' && $estimatedCount > $backgroundVariableThreshold) || ($data['sms_type'] !== 'VARIABLES' && $estimatedCount > $backgroundThreshold)) {
                return $this->processInBackground($processedData, $user);
            }

            // Process immediately for smaller datasets
            return $this->processImmediately($processedData, $user);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Log::error('SMS statistics processing failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Process statistics immediately for small datasets
     */
    private function processImmediately(array $data, User $user): array
    {
        $result = $this->processingService->processRequest($data, $user);
        
        // Validate message count
        $this->dataProcessor->validateMessageCount($result['count']);
        
        return $this->dataProcessor->prepareResponseData($result, 0, 'immediate');
    }

    /**
     * Process statistics in background for large datasets
     */
    private function processInBackground(array $data, User $user): array
    {
        // Create a StatisticsProcessing record
        $processingId = \App\Models\StatisticsProcessing::generateProcessingId();
        $statisticsProcessing = \App\Models\StatisticsProcessing::create([
            'processing_id' => $processingId,
            'user_id' => $user->id,
            'workspace_id' => $data['workspace']->id ?? null,
            'channel_id' => $data['channel']->id ?? null,
            'all_numbers' => $data['all_numbers'],
            'sender_name' => $data['from'],
            'message' => $data['message'],
            'send_time_method' => $data['send_time_method'],
            'send_time' => $data['send_time'] ?? null,
            'sms_type' => $data['sms_type'],
            'repeation_times' => $data['repeation_times'] ?? null,
            'excel_file' => $data['excle_file'] ?? null,
            'message_length' => $data['leng'],
            'status' => \App\Models\StatisticsProcessing::STATUS_PENDING
        ]);

        // Dispatch the background job
        \App\Jobs\ProcessStatisticsJob::dispatch($statisticsProcessing, $user)
            ->onQueue('sms-normal');

        return [
            'processing_id' => $processingId,
            'status' => 'processing',
            'message' => __('message.statistics_processing_started'),
            'processing_type' => 'background'
        ];
    }

}
