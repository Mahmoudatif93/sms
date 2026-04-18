<?php

namespace App\Services\Sms;

use App\Models\User;
use App\Helpers\Sms\MessageHelper;

class SmsDataProcessor
{
    /**
     * Process and normalize SMS message data
     */
    public function processMessageData(array $data, User $user): array
    {
        // Ensure message is properly decoded
        $data['message'] = decodeUnicodeEscape($data['message']);
        
        // Calculate message length
        $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
        
        // Set sender name
        $data['sender_name'] = $data['from'] ?? null;
        
        // Set workspace ID if available
        $data['workspace_id'] = $data['workspace']->id ?? null;
        
        // Process calendar message if needed
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
        
        return $data;
    }

    /**
     * Process message data for statistics only (no calendar processing)
     */
    public function processStatisticsData(array $data, User $user): array
    {
        // Basic processing without calendar message modification
        $data['message'] = decodeUnicodeEscape($data['message']);
        $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
        $data['sender_name'] = $data['from'] ?? null;
        $data['workspace_id'] = $data['workspace']->id ?? null;
        $data['user_id'] = $user->id;
        
        return $data;
    }

    /**
     * Prepare data for message creation
     */
    public function prepareMessageCreationData(array $processedData, User $user, bool $isReviewMessage = false): array
    {
        $isReviewFromParent = MessageHelper::isReviewFromParent($user->id);
        
        return [
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
            'advertising' => $isReviewFromParent ? 2 : ($isReviewMessage ? 1 : 0),
            'sent_cnt' => 0,
            'lang' => MessageHelper::calcMessageLang($processedData['message']),
        ];
    }

    /**
     * Check if user has empty sender (for test mode)
     */
    public function handleEmptySender(array $data, User $user): array
    {
        // if organization has no senders, set default sender and message
        if ($user->currentWorkspace()->organization->senders->isEmpty()) {
            $data['from'] = 'Dreams';
            $data['message'] = trans('message.msg_test_sms');
        }
        return $data;
    }

    /**
     * Validate message count limits
     */
    public function validateMessageCount(int $count): void
    {
        if ($count > 100001) {
            throw new \InvalidArgumentException(
                trans('message.msg_error_exceeded_maximum_number', ['number' => 100000])
            );
        }
    }

    /**
     * Calculate statistics from entries
     */
    public function calculateStatistics(array $entries): array
    {
        return [
            'count' => array_reduce($entries, function ($carry, $entry) {
                return $carry + $entry['cnt'];
            }, 0),
            'cost' => array_reduce($entries, function ($carry, $entry) {
                return $carry + $entry['cost'];
            }, 0)
        ];
    }

    /**
     * Prepare final response data
     */
    public function prepareResponseData(array $processedData, int $messageId = 0, string $processingType = 'immediate'): array
    {
        return [
            'message_id' => $messageId,
            'entries' => $processedData['entries'] ?? [],
            'count' => $processedData['count'],
            'cost' => $processedData['cost'],
            'can_send' => $processedData['cost'] > 0,
            'processing_type' => $processingType
        ];
    }
}
