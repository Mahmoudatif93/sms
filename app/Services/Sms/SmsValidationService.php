<?php

namespace App\Services\Sms;

use App\Helpers\Sms\MessageValidationHelper;
use App\Models\User;
use App\Exceptions\UserInactiveException;
use Illuminate\Validation\ValidationException;

class SmsValidationService
{
    // Constants for validation limits
    private const MAX_MESSAGE_COUNT = 100000;
    private const REQUIRED_FIELDS_FOR_SENDING = ['message', 'from', 'send_time_method'];
    private const REQUIRED_FIELDS_FOR_STATISTICS = ['message'];

    /**
     * Validate message content for bad words
     */
    public function validateMessageContent(string $message): void
    {
        MessageValidationHelper::validateBadWords($message);
    }

    /**
     * Validate advertisement sending time
     */
    public function validateAdsTime(string $senderName, string $sendTimeMethod, ?string $sendTime = null): void
    {
        MessageValidationHelper::validateAdsTimeSend($senderName, $sendTimeMethod, $sendTime);
    }

    /**
     * Validate if user is active
     */
    public function validateUserActive(User $user): void
    {
        if (!$user->active || $user->active != 1) {
            throw new UserInactiveException();
        }
    }

    /**
     * Validate user permissions and status
     */
    public function validateUser(User $user): void
    {
        // Check if user is active
        $this->validateUserActive($user);

        // Check if user balance is expired
        if ($user->isBalanceExpired()) {
            $this->throwValidationException('balance', trans('message.msg_error_expired_blance'));
        }
    }

    /**
     * Validate message count limits
     */
    public function validateMessageCount(int $count): void
    {
        if ($count > self::MAX_MESSAGE_COUNT + 1) {
            $this->throwValidationException(
                'count',
                trans('message.msg_error_exceeded_maximum_number', ['number' => self::MAX_MESSAGE_COUNT])
            );
        }
    }

    /**
     * Validate required fields exist in data
     */
    public function validateRequiredFields(array $data, array $requiredFields): void
    {
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missingFields[] = $field;
            }
        }

        if (!empty($missingFields)) {
            $this->throwValidationException(
                'required_fields',
                'Missing required fields: ' . implode(', ', $missingFields)
            );
        }
    }

    /**
     * Validate SMS message data with timing constraints
     */
    public function validateMessageWithTiming(array $data): void
    {
        // Validate message content
        $this->validateMessageContent($data['message']);

        // Validate advertisement sending time if applicable
        if (isset($data['send_time_method'])) {
            $senderName = $this->extractSenderName($data);
            $this->validateAdsTime(
                $senderName,
                $data['send_time_method'],
                $data['send_time'] ?? null
            );
        }
    }

    /**
     * Comprehensive validation for SMS sending
     */
    public function validateForSending(array $data, User $user): void
    {
        // Validate required fields
        $this->validateRequiredFields($data, self::REQUIRED_FIELDS_FOR_SENDING);

        // Validate user status
        $this->validateUser($user);

        // Validate message content and timing
        $this->validateMessageWithTiming($data);

        // Validate message count if provided
        if (isset($data['count'])) {
            $this->validateMessageCount($data['count']);
        }
    }

    /**
     * Validate for statistics processing only
     */
    public function validateForStatistics(array $data, User $user): void
    {
        // Validate required fields
        $this->validateRequiredFields($data, self::REQUIRED_FIELDS_FOR_STATISTICS);

        // Validate user is active (lighter validation for statistics)
        $this->validateUserActive($user);

        // Validate message content and timing
        $this->validateMessageWithTiming($data);
    }

    /**
     * Validate for background job processing
     */
    public function validateForBackgroundProcessing(array $data, User $user): void
    {
        // Full user validation for background processing
        $this->validateUser($user);

        // Validate message content
        $this->validateMessageContent($data['message']);

        // Validate count if provided
        if (isset($data['count'])) {
            $this->validateMessageCount($data['count']);
        }
    }

    /**
     * Extract sender name from data array
     */
    private function extractSenderName(array $data): string
    {
        return $data['from'] ?? $data['sender_name'] ?? '';
    }

    /**
     * Create and throw ValidationException with proper error structure
     */
    private function throwValidationException(string $field, string $message): void
    {
        $validator = validator([], []);
        $validator->errors()->add($field, $message);
        throw new ValidationException($validator);
    }
}
