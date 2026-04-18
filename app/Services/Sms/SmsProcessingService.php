<?php
namespace App\Services\Sms;

use App\Helpers\Sms\MessageValidationHelper;
use App\Helpers\Sms\MessageHelper;
use Illuminate\Validation\ValidationException;
use App\Services\FileUploadService;
use App\Services\Sms;   
use App\Models\Message;
use App\Models\Outbox;
use App\Models\ContactEntity;
use App\Class\SmsProcessorFactory;
use App\Models\Service as MService;
use App\Enums\Service as EnumService;
use App\Traits\WalletManager;
class SmsProcessingService
{
    use WalletManager;
    protected $sms;
    protected $fileUploadService;

    public function __construct(Sms $sms, FileUploadService $fileUploadService)
    {
        $this->sms = $sms;
        $this->fileUploadService = $fileUploadService;
    }

    public function processRequest(array $data, $user)
    {
        try {
            $data['message'] = decodeUnicodeEscape($data['message']);
            $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
            $data['sender_name'] = $data['from'] ??null;
            $data['workspace_id'] = $data['workspace']->id ??null;

            // Validate message content
            MessageValidationHelper::validateBadWords($data['message']);
            if (isset($data['send_time_method'])) {
                MessageValidationHelper::validateAdsTimeSend($data['from'] ?? $data['sender_name'], $data['send_time_method'], $data['send_time'] ?? null);
            }
            
            $data['message'] = MessageHelper::calanderMessage(
                $data['message'], 
                $data['sms_type'], 
                $user->id, 
                $data['from'] ?? $data['sender_name'],
                $data['calendar_time'] ?? null,
                $data['reminder'] ?? null,
                $data['reminder_text'] ?? null,
                $data['location_url'] ?? null
            );

            // Process numbers
            $all_numbers = $this->sms->processNumbers($data['all_numbers']);
            $entries = [];
            $numberArr = [];
            
            $this->sms->processAllNumbers(
                $all_numbers, 
                $data['excle_file'] ?? null, 
                $entries, 
                $data['leng'], 
                $numberArr, 
                $data['message'], 
                $data['sms_type'], 
                $user
            );

            // Calculate totals
            $data['all_numbers_json'] = json_encode($numberArr);
            $data['entries'] = array_values($entries);
            $data['count'] = array_reduce($data['entries'], function ($carry, $entry) {
                return $carry + $entry['cnt'];
            }, 0);
            $data['cost'] = array_reduce($data['entries'], function ($carry, $entry) {
                return $carry + $entry['cost'];
            }, 0);

            // Remove the hard limit check - now handled by background processing
            // The limit is now configurable and handled in the controller

            return $data;

        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Process request with support for background processing
     * Returns either immediate results or background processing info
     */
    public function processRequestWithBackgroundSupport(array $data, $user, $backgroundThreshold = 10000)
    {
        try {
            $data['message'] = decodeUnicodeEscape($data['message']);
            $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
            $data['sender_name'] = $data['from'] ?? null;
            $data['workspace_id'] = $data['workspace']->id ?? null;

            // Validate message content
            MessageValidationHelper::validateBadWords($data['message']);
            if (isset($data['send_time_method'])) {
                MessageValidationHelper::validateAdsTimeSend($data['from'] ?? $data['sender_name'], $data['send_time_method'], $data['send_time'] ?? null);
            }

            // Estimate number count for processing strategy
            $estimatedCount = $this->estimateNumberCount(
                $data['all_numbers'],
                $data['sms_type'],
                $data['excle_file'] ?? null,
                $data['workspace_id']??null
            );

            if ($estimatedCount > $backgroundThreshold) {
                // Return background processing info
                return [
                    'processing_type' => 'background',
                    'estimated_count' => $estimatedCount,
                    'threshold' => $backgroundThreshold
                ];
            }

            // Process immediately for smaller datasets
            return $this->processRequest($data, $user);

        } catch (ValidationException $e) {
            throw $e;
        }
    }

    /**
     * Estimate number count for processing strategy
     * This method provides a quick estimation without full processing
     */
    public function estimateNumberCount($allNumbers, $smsType, $excelFilePath = null,$workspaceId=null)
    {
        // For Excel files, estimate based on actual file analysis
        if ($allNumbers === 'excel_file' || $smsType === 'VARIABLES') {
            return $this->estimateExcelFileCount($excelFilePath);
        }

        // For group numbers, estimate based on actual group size
        if (strpos($allNumbers, 'G') === 0) {
            return $this->estimateGroupCount($allNumbers,$workspaceId);
        }

        // For ads numbers, estimate based on actual tag size
        if (strpos($allNumbers, 'A') === 0) {
            return $this->estimateAdsCount($allNumbers);
        }

        // For individual numbers, do actual count (it's fast)
        $processedNumbers = $this->sms->processNumbers($allNumbers);
        return count($processedNumbers);
    }

    /**
     * Estimate Excel file row count using dedicated service
     */
    private function estimateExcelFileCount($filePath)
    {
        if (!$filePath) {
            return 50000; // Conservative fallback
        }

        try {
            $excelEstimationService = app(\App\Services\Sms\ExcelEstimationService::class);
            
            return $excelEstimationService->estimateRowCount($filePath);
        } catch (\Exception $e) {
            \Log::warning("Excel estimation service failed", [
                'file' => $filePath,
                'error' => $e->getMessage()
            ]);

            return 50000; // Conservative fallback
        }
    }

    /**
     * Estimate group contact count
     */
    private function estimateGroupCount($groupNumber,$workspaceId)
    {
        try {
            $listId = substr($groupNumber, 1); // Remove 'G' prefix

            // Quick count from database
            $count =  ContactEntity::with(['identifiers', 'lists'])
    ->where('workspace_id', $workspaceId)
    ->whereHas('lists', function ($query) use ($listId) {
        $query->where('lists.id', $listId);
    })
    ->whereHas('identifiers', function ($query) {
        $query->where('key', 'phone');
    })
    ->get();

            return $count ?: 5000; // Fallback estimate

        } catch (\Exception $e) {
            \Log::warning("Failed to estimate group count", [
                'group' => $groupNumber,
                'error' => $e->getMessage()
            ]);

            return 5000; // Conservative fallback
        }
    }

    /**
     * Estimate ads contact count
     */
    private function estimateAdsCount($adsNumber)
    {
        try {
            $tagId = substr($adsNumber, 1); // Remove 'A' prefix

            // Quick count from database
            $count = \App\Models\AdContact::whereHas('tags', function ($query) use ($tagId) {
                $query->where('id', $tagId);
            })->count();

            return $count ?: 10000; // Fallback estimate

        } catch (\Exception $e) {
            \Log::warning("Failed to estimate ads count", [
                'ads' => $adsNumber,
                'error' => $e->getMessage()
            ]);

            return 10000; // Conservative fallback
        }
    }

    /**
     * Process numbers in batch for background processing
     * This method is optimized for large datasets
     */
    public function processNumbersBatch(array $numbers, $messageLong, $message, $smsType, $user, $excelFile = null)
    {
        $entries = [];
        $numberArr = [];

        // Get countries for the user
        if ($user) {
            $countries = \App\Models\Country::get_active_by_user($user->id, $user->is_international ?? 0);
        } else {
            $countries = \App\Models\Country::where('id', 966)->get();
        }

        // Process numbers using the SMS service
        $this->sms->processAllNumbers($numbers, $excelFile, $entries, $messageLong, $numberArr, $message, $smsType, $user);

        return [
            'entries' => array_values($entries),
            'numberArr' => $numberArr,
            'count' => array_reduce($entries, function ($carry, $entry) {
                return $carry + $entry['cnt'];
            }, 0),
            'cost' => array_reduce($entries, function ($carry, $entry) {
                return $carry + $entry['cost'];
            }, 0)
        ];
    }

    public function createMessage($processedData, $user, $isReviewMessage = false)
    {
        return \DB::transaction(function () use ($processedData, $user, $isReviewMessage) {
            $data = [
                'channel' => 'DIRECT',
                'user_id' => $user->id,
                'workspace_id' => $processedData['workspace_id'],
                'text' => $processedData['message'],
                'count' => $processedData['count'],
                'cost' => $processedData['cost'],
                'length' => $processedData['leng'],
                'creation_datetime' => \Carbon\Carbon::now(),
                'sending_datetime' => $processedData['send_time'] ?? null,
                'repeation_period' => 0,
                'repeation_times' => 0,
                'variables_message' => $processedData['sms_type'] == "VARIABLES" ? 1 : 0,
                'sender_name' => $processedData['from'] ?? $processedData['sender_name'],
                'excel_file_numbers' => $processedData['excle_file'] ?? null,
                'all_numbers' => $processedData['all_numbers_json'],
                'encrypted' => $processedData['sms_type'] == "ADS" ? 1 : 0,
                'auth_code' => randomAuthCode(),
                'advertising' => $isReviewMessage ? 1 : 0,
                'sent_cnt' => 0,
                'lang' => MessageHelper::calcMessageLang($processedData['message']),
            ];

            $outbox = Outbox::create($data);
            $data['encrypted'] = 0;
            $message = Message::create($data);
            $outbox->message_id = $message->id;
            $outbox->save();

            $processor = SmsProcessorFactory::createProcessor($data['count']);
            if (!$processor->process($outbox)) {
                throw new \Exception("Message details insert failed");
            }

            if ($isReviewMessage) {
                MessageHelper::SendReviewMessage(
                    $processedData['message'],
                    $processedData['from'] ?? $processedData['sender_name'],
                    $message->id
                );
            }
            if ( $message->sending_datetime == null && !$isReviewMessage  ) {//&& $message->variables_message == 0
                $processor->sendMessage($message->id);
            }

            $this->fileUploadService->deleteFileOss($data['excel_file_numbers'] ?? null);

            return $message;
        });
    }

    /**
     * Validate user balance and deduct cost for background sending
     */
    public function validateAndDeductBalance($user, $workspace, $cost, $description = "SMS Campaign")
    {
        try {
            // Get wallet using the same logic as sendV2
            $wallet = $this->getObjectWallet(
                $workspace,
                MService::where('name', EnumService::SMS)->value('id'),
                $user->id
            );
            if (!$wallet) {
                return [
                    'success' => false,
                    'message' => 'Wallet not found'
                ];
            }

            // Check and deduct balance
            if (!$this->changeBalance($wallet, -1 * $cost, "sms", $description)) {
                return [
                    'success' => false,
                    'message' => trans('message.msg_error_insufficient_balance')
                ];
            }

            return [
                'success' => true,
                'wallet' => $wallet
            ];

        } catch (\Exception $e) {
            \Log::error("Balance validation failed", [
                'user_id' => $user->id,
                'cost' => $cost,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}