<?php

namespace App\Services;

use App\Models\ManagementNotifiable;
use App\Notifications\ManagementNotification;
use Illuminate\Support\Facades\Log;

class ManagementNotificationService
{
    /**
     * Send a notification to the management team
     *
     * @param string $subject The notification subject
     * @param string $message The notification message
     * @param array $data Additional data to include in the notification
     * @param string $type The type of notification (payment, channel, user, etc.)
     * @param string $actionUrl Optional URL for action button
     * @param string $actionText Optional text for action button
     * @return bool Whether the notification was sent successfully
     */
    public static function notify(
        string $subject,
        string $message,
        array $data = [],
        string $type = 'general',
        string $actionUrl = null,
        string $actionText = null
    ): bool {
        $managementEmails = self::getManagementEmails();
        
        if (empty($managementEmails)) {
            Log::warning('No management emails configured. Notification not sent.');
            return false;
        }
        
        $sent = true;
        foreach ($managementEmails as $email) {
            $lang = $email['lang'] ?? env('MANAGEMENT_LANG', 'en');
            $notifiable = new ManagementNotifiable($email['address'], $lang);
            
            try {
                $notifiable->notify(new ManagementNotification(
                    $subject,
                    $message,
                    $data,
                    $type,
                    $actionUrl,
                    $actionText
                ));
            } catch (\Exception $e) {
                Log::error("Failed to send management notification to {$email['address']}: " . $e->getMessage());
                $sent = false;
            }
        }
        
        return $sent;
    }
    
    /**
     * Get the list of management emails from the environment
     *
     * @return array Array of management emails with language preferences
     */
    private static function getManagementEmails(): array
    {
        $managementEmail = env('MANAGEMENT_EMAIL');
        $managementLang = env('MANAGEMENT_LANG', 'ar');
        
        // If multiple emails are provided (comma-separated)
        if (strpos($managementEmail, ',') !== false) {
            $emails = array_map('trim', explode(',', $managementEmail));
            return array_map(function($email) use ($managementLang) {
                return [
                    'address' => $email,
                    'lang' => $managementLang
                ];
            }, $emails);
        }
        
        // Single email
        if ($managementEmail) {
            return [
                [
                    'address' => $managementEmail,
                    'lang' => $managementLang
                ]
            ];
        }
        
        return [];
    }
    
    /**
     * Send a payment notification to management
     *
     * @param string $organizationName
     * @param string $channelName
     * @param float $amount
     * @param string $platform
     * @param string $channelId
     * @return bool
     */
    public static function notifyPayment(
        string $organizationName,
        string $channelName,
        float $amount,
        string $platform,
        string $channelId
    ): bool {
        $locale = $notifiable->lang ?? config('app.locale');
        app()->setLocale($locale);
        $subject = trans('notification.email.management.payment.title')." - {$organizationName}";
        $message = trans('notification.email.management.payment.processed',['channelName'=>$channelName,'amount'=>$amount,'organizationName'=> $organizationName]);
        $data = [
            'organization_name' => $organizationName,
            'channel_name' => $channelName,
            'amount' => $amount,
            'platform' => $platform,
            'channel_id' => $channelId,
            'date' => now()->toIso8601String()
        ];
        
        $actionUrl = "https://admin.dreams.sa/channels/sms-info/{$channelId}";
        
        return self::notify($subject, $message, $data, 'payment', $actionUrl, trans('notification.view_channel'));
    }
    
    /**
     * Send a channel status change notification to management
     *
     * @param string $organizationName
     * @param string $channelName
     * @param string $platform
     * @param string $oldStatus
     * @param string $newStatus
     * @param string $channelId
     * @return bool
     */
    public static function notifyChannelStatusChange(
        string $organizationName,
        string $channelName,
        string $platform,
        string $oldStatus,
        string $newStatus,
        string $channelId
    ): bool {
        $subject = "Channel Status Changed - {$channelName}";
        $message = "Channel \"{$channelName}\" status has changed from {$oldStatus} to {$newStatus} for organization \"{$organizationName}\".";
        $data = [
            'organization_name' => $organizationName,
            'channel_name' => $channelName,
            'platform' => $platform,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'channel_id' => $channelId,
            'date' => now()->toIso8601String()
        ];
        
        $actionUrl = "https://portal.dreams.sa/channels/sms-info/{$channelId}";
        
        return self::notify($subject, $message, $data, 'channel_status', $actionUrl, 'View Channel');
    }
    
    /**
     * Send a new channel creation notification to management
     *
     * @param string $organizationName
     * @param string $channelName
     * @param string $platform
     * @param string $status
     * @param string $channelId
     * @return bool
     */
    public static function notifyNewChannel(
        string $organizationName,
        string $channelName,
        string $platform,
        string $status,
        string $channelId
    ): bool {
        $subject = "New Channel Created - {$channelName}";
        $message = "A new {$platform} channel \"{$channelName}\" has been created for organization \"{$organizationName}\" with status {$status}.";
        $data = [
            'organization_name' => $organizationName,
            'channel_name' => $channelName,
            'platform' => $platform,
            'status' => $status,
            'channel_id' => $channelId,
            'date' => now()->toIso8601String()
        ];
        
        $actionUrl = "https://admin.dreams.sa/senders/create-sender?id={$channelId}";
        
        return self::notify($subject, $message, $data, 'new_channel', $actionUrl, 'View Channel');
    }
    
    /**
     * Send a channel deletion notification to management
     *
     * @param string $organizationName
     * @param string $channelName
     * @param string $platform
     * @return bool
     */
    public static function notifyChannelDeletion(
        string $organizationName,
        string $channelName,
        string $platform
    ): bool {
        $subject = "Channel Deleted - {$channelName}";
        $message = "Channel \"{$channelName}\" ({$platform}) has been deleted from organization \"{$organizationName}\".";
        $data = [
            'organization_name' => $organizationName,
            'channel_name' => $channelName,
            'platform' => $platform,
            'date' => now()->toIso8601String()
        ];
        
        return self::notify($subject, $message, $data, 'channel_deletion');
    }
    
    /**
     * Send a notification about a new sender name request
     * 
     * @param string $organizationName
     * @param string $senderName
     * @param string $channelId
     * @return bool
     */
    public static function notifySenderNameRequest(
        string $organizationName,
        string $senderName,
        string $channelId
    ): bool {
        $subject = "New Sender Name Request - {$senderName}";
        $message = "Organization \"{$organizationName}\" has requested a new sender name: \"{$senderName}\".";
        $data = [
            'organization_name' => $organizationName,
            'sender_name' => $senderName,
            'channel_id' => $channelId,
            'date' => now()->toIso8601String()
        ];
        
        $actionUrl = "https://portal.dreams.sa/admin/senders";
        
        return self::notify($subject, $message, $data, 'sender_request', $actionUrl, 'View Senders');
    }
}