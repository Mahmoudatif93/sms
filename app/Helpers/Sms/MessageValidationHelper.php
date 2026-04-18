<?php

namespace App\Helpers\Sms;

use App\Models\Setting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class MessageValidationHelper 
{
    /**
     * Validate the advertisement sending time based on sender name, method, and send time.
     *
     * @param string $sender_name
     * @param string $send_time_method
     * @param string|null $send_time
     * @throws ValidationException
     */
    public static function validateAdsTimeSend($sender_name, $send_time_method, $send_time)
    {

        if (SenderHelper::isAdSender($sender_name) && !self::isValidSendTime($send_time_method, $send_time)) {
            self::throwValidationException('sender_name', trans('message.msg_error_time_exceeded'));
        }

    }
    /**
     * Validate message for bad words.
     *
     * @param string $message
     * @throws ValidationException
     */
    public static function validateBadWords($message)
    {

        $message_words = explode(" ", $message);
        $bad_words = explode(",", Setting::get_by_name('bad_words'));
        foreach ($message_words as $word) {
            if (in_array($word, $bad_words)) {
                self::throwValidationException('message', trans('message.msg_error_message_with_bad_words'));
            }
        }
    }

    /**
     * Validate the send time based on the method and provided send time.
     *
     * @param string $send_time_method
     * @param string|null $send_time
     * @return bool
     */
    private static function isValidSendTime($send_time_method, $send_time)
    {
        $currentTime = strtotime(server_time());
        $startTime = strtotime("09:00:00");
        $endTime = strtotime("22:00:00");

        if ($send_time_method === "NOW") {
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }

        if ($send_time_method === "LATER" && $send_time) {
            $time = (new \DateTime($send_time))->format('H:i');
            $scheduledTime = strtotime($time);

            return $scheduledTime >= $startTime && $scheduledTime <= $endTime;
        }

        return true;
    }

    /**
     * Throw a validation exception with the given field and message.
     *
     * @param string $field
     * @param string $message
     * @throws ValidationException
     */
    public static function throwValidationException($field, $message)
    {
        // throw new InvalidArgumentException($message);
        $validator = Validator::make([], []);
        $validator->errors()->add($field, $message);
        throw new ValidationException($validator);
    }


    
}