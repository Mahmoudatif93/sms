<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Http\Controllers\SmsApiController;

class SendLoginNotificationService extends SmsApiController
{
    /**
     * Send login notification to the user.
     *
     * @param \App\Models\User $user
     */
    public function sendEmailNotification(
        $email,
        $Emailsubject,
        $EmailTitle,
        $Loginmessage,
        $viewName = null,
        $attachments = [],
        $inline_attachments = [],
        $cc = [],
        $bcc = [],
        $message = [],
        $otpType=null
    ) {
        // Dispatch the job to send Email notification
        SendEmail::dispatch(
            $email,
            $Emailsubject,
            $EmailTitle,
            $Loginmessage,
            $viewName,
            $attachments = [],
            $inline_attachments = [],
            $cc = [],
            $bcc = [],
            $message,$otpType
        );
    }
    public function sendSmsNotification($system_sms_sender, $numbers, $Loginmessage, $type, $user_id = null)
    {
        //   job to send the Sms notification
        $type === "admin"
            ? $this->sendSmsAdmin($system_sms_sender, $numbers, $Loginmessage)
            : ($type === "user"
                ? $this->sendSmsUser($user_id, $system_sms_sender, $numbers, $Loginmessage)
                : null);
    }
}
