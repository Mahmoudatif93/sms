<?php

namespace App\Helpers\Sms;
use App\Models\Setting;
use App\Models\WhiteListUrl;
use App\Services\NotificationService;
use App\Contracts\NotificationManagerInterface;
use Illuminate\Support\Facades\Http;
class MessageHelper
{
    public static function SendReviewMessage($message, $senderName = null, $messageId = null) {
        try {
            // Get the centralized notification manager
            $notificationManager = app(NotificationManagerInterface::class);

            // Prepare template variables
            $templateVariables = [
                'message_content' => htmlspecialchars($message),
                'sender_name' => $senderName ?? 'غير محدد',
                'message_id' => $messageId ?? 'غير محدد',
                'review_time' => date('Y-m-d H:i:s')
            ];

            // Get review channel settings from config or database
            $reviewChannelId = config('notifications.available_channels.telegram.channels.review', '@dreams_review');
            // Define recipients for review channel
            $recipients = [
                ['type' => 'telegram', 'identifier' => $reviewChannelId]
            ];

            // Send via centralized notification system
            $result = $notificationManager->sendFromTemplate(
                'message_review',
                $recipients,
                $templateVariables,
                ['telegram'],
                [
                    'sender_type' => 'admin',
                    'priority' => 'high'
                ]
            );

            if ($result['success']) {
                \Log::info('Review message sent via centralized notification system', [
                    'message_id' => $messageId,
                    'sender' => $senderName,
                    'notification_log_id' => $result['notification_log_id'] ?? null,
                    'template_used' => 'message_review'
                ]);
            } else {
                \Log::error('Failed to send review message via centralized system', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'message_id' => $messageId,
                    'sender' => $senderName
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            \Log::error('Exception in SendReviewMessage', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'sender' => $senderName,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'telegram'
            ];
        }
    }

    public static function SendReviewMessageToParent($message, $parentInfo = null, $childInfo = null) {
        try {
            $notificationService = new NotificationService();

            // Check notification settings
            $advertising_message = Setting::get_by_name('advertising_message');

            if ($advertising_message == "SMS" || $advertising_message == "BOTH") {
                $recive_numbers = explode(",", Setting::get_by_name('receiver_number_advertising'));
                // TODO: Implement SMS sending logic here if needed
                \Log::info('SMS notification enabled for parent review', [
                    'receivers' => $recive_numbers
                ]);
            }

            // Prepare the parent review message for Telegram
            $reviewMessage = "👨‍👩‍👧‍👦 <b>رسالة من حساب فرعي تحتاج مراجعة</b>\n\n";
            $reviewMessage .= "📝 <b>المحتوى:</b>\n" . htmlspecialchars($message) . "\n\n";

            if ($childInfo) {
                $reviewMessage .= "👤 <b>الحساب الفرعي:</b> " . htmlspecialchars($childInfo) . "\n";
            }

            if ($parentInfo) {
                $reviewMessage .= "👨‍💼 <b>الحساب الرئيسي:</b> " . htmlspecialchars($parentInfo) . "\n";
            }

            $reviewMessage .= "⏰ <b>الوقت:</b> " . date('Y-m-d H:i:s');

            // Send via Telegram to admin channel
            $result = $notificationService->sendTelegramMessage($reviewMessage, 'admin');

            if ($result['success']) {
                \Log::info('Parent review message sent to Telegram successfully', [
                    'parent_info' => $parentInfo,
                    'child_info' => $childInfo,
                    'telegram_message_id' => $result['data']['result']['message_id'] ?? null
                ]);
            } else {
                \Log::error('Failed to send parent review message to Telegram', [
                    'error' => $result['error'],
                    'parent_info' => $parentInfo,
                    'child_info' => $childInfo
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            \Log::error('Exception in SendReviewMessageToParent', [
                'error' => $e->getMessage(),
                'parent_info' => $parentInfo,
                'child_info' => $childInfo,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'telegram'
            ];
        }
    }

    /**
     * Send notification when a new live chat conversation is started
     *
     * @param array $contactInfo Contact information from pre-chat form
     * @param string $conversationId Conversation ID
     * @param string|null $widgetName Widget/Channel name
     * @return array
     */
    public static function sendLiveChatNotification($contactInfo, $conversationId, $widgetName = null) {
        try {
            // Get the centralized notification manager
            $notificationManager = app(NotificationManagerInterface::class);

            // Prepare template variables
            $templateVariables = [
                'contact_name' => htmlspecialchars($contactInfo['name'] ?? 'غير محدد'),
                'contact_email' => htmlspecialchars($contactInfo['email'] ?? 'غير محدد'),
                'contact_phone' => htmlspecialchars($contactInfo['phone'] ?? 'غير محدد'),
                'channel_name' => htmlspecialchars($widgetName ?? 'غير محدد'),
                'conversation_id' => $conversationId,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            // Get admin channel settings from config
            $adminChannelId = config('notifications.available_channels.telegram.channels.review', '@dreams_admin');
            // Define recipients for admin channel
            $recipients = [
                ['type' => 'telegram', 'identifier' => $adminChannelId]
            ];

            // Send via centralized notification system
            $result = $notificationManager->sendFromTemplate(
                'livechat_new_conversation',
                $recipients,
                $templateVariables,
                ['telegram'],
                [
                    'sender_type' => 'system',
                    'priority' => 'high'
                ]
            );
            if ($result['success']) {
                \Log::info('Live chat notification sent via centralized notification system', [
                    'conversation_id' => $conversationId,
                    'contact_name' => $contactInfo['name'] ?? null,
                    'notification_log_id' => $result['notification_log_id'] ?? null,
                    'template_used' => 'livechat_new_conversation'
                ]);
            } else {
                \Log::error('Failed to send live chat notification via centralized system', [
                    'error' => $result['error'] ?? 'Unknown error',
                    'conversation_id' => $conversationId
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            \Log::error('Exception in sendLiveChatNotification', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'telegram'
            ];
        }
    }

    public static function sendApprovalNotification($message) {
        try {
            $notificationService = new NotificationService();

            // Send via Telegram to admin channel
            $result = $notificationService->sendTelegramMessage($message, 'admin');

            if ($result['success']) {
                \Log::info('Approval notification sent to Telegram successfully', [
                    'telegram_message_id' => $result['data']['result']['message_id'] ?? null
                ]);
            } else {
                \Log::error('Failed to send approval notification to Telegram', [
                    'error' => $result['error']
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            \Log::error('Exception in sendApprovalNotification', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => 'telegram'
            ];
        }
    }

    public static function calcMessageLang($message){
		if (preg_match("/^([-a-zA-Z0-9_ \n\r\s,;:.!@£?#$&*+=\/<>\'\"\^{})(%\-|])*$/",$message)){
			return "EN";
		}else{
            return "AR";
		}	
	}   

    public static function isReviewMessage($message, $senderName, $allowUrl,$allowSendBlock,$countMessage)
    {
        $message_words = preg_split('/\s+/', $message);
        foreach ($message_words as $word) {
            if (!self::isAllowedUrl($word, $allowUrl) || self::isAdvertising($word, $senderName) || self::isOverloadCoutSms($senderName,$allowSendBlock,$countMessage)) {
                return true;
            }
        }
        return false;
    }

    public static function isReviewFromParent($user_id){
        //TODO: check if user has parent
        $user = \App\Models\User::find($user_id);
        if($user->parent_id){
            return true;
        }
        return false;
    }

    public static function calanderMessage($message,$sms_type,$clientId,$summary, $time, $reminder, $reminderText, $location){
        if($sms_type == "CALENDAR"){
            $coordinates = get_lat_long($location);
           $response = self::calanderUrl($clientId,$summary, $message, $time, $reminder, $reminderText, $coordinates['lat'], $coordinates['long'], extractLocationTitle($location));
           if($response->status <> 1){
            MessageValidationHelper::throwValidationException('sms_type', trans('message.msg_error_calendar'));
           }
           return $message."\n".$response->url;
        }
        return $message;
    }

    /**
     * Check if a word is an allowed URL.
     *
     * @param string $word
     * @param bool $allowUrl
     * @return bool
     */
    private static function isAllowedUrl($word, $allowUrl)
    {
        if ($allowUrl || !self::isUrl($word)) {
            return true; // Not a URL, or URLs are allowed
        }
        
        $parsed = parse_url($word);
        if (!$parsed || !isset($parsed['host'])) {
            return false; // Invalid URL format, consider it not allowed
        }
        
        return WhiteListUrl::existsByUrl($parsed['host']);
    }

    /**
     * Check if a word is advertising based on sender name.
     *
     * @param string $word
     * @param string $senderName
     * @return bool
     */
    private static function isAdvertising($word, $sender_name)
    {
        if (SenderHelper::isAdSender($sender_name)) {
            return false;
        }

        $advertisingWords = explode(",", Setting::get_by_name('advertising_words'));
        return in_array($word, $advertisingWords);


    }

    /**
     * Check if a word is advertising based on sender name.
     *
     * @param string $senderName
     * @param bool $allowSendBlock
     * @param bool $countMessage
     * @return bool
     */
    private static function isOverloadCoutSms($senderName,$allowSendBlock,$countMessage){
        if (SenderHelper::isAdSender($senderName) || $allowSendBlock) {
            return false;
        }
        return $countMessage > Setting::get_by_name('variable_sms_send_without_warning');
    }
    /**
     * Check if a string is a valid URL.
     *
     * @param string $text
     * @return bool|string
     */
    private static function isUrl($url)
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            if (filter_var("http://$url", FILTER_VALIDATE_URL) === false) {
                return false;
            }
            $url = "http://$url";
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $host)) {
            return false;
        }
        return $url;
    }


    private static function calanderUrl($clientId,$summary, $description, $time, $reminder, $reminderText, $lat, $long, $location){
        $data = array(
            'client_id' => $clientId,
            'summary' => $summary,
            'description' => $description,
            'specific_time' => $time,
            'alarm' => $reminder,
            'alarm_text' => $reminderText,
            'lat' => $lat,
            'long' => $long,
            'location' => $location
        );
        $response = Http::post('https://short.dreams.sa/api/links/meeting',$data);
        return json_decode($response->body());
    }
}