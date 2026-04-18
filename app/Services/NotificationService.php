<?php

namespace App\Services;

use App\Contracts\NotificationChannelInterface;
use App\Services\Notifications\TelegramNotificationChannel;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private array $channels = [];
    private array $defaultChannels = [];

    public function __construct()
    {
        $this->registerDefaultChannels();
    }

    /**
     * Register default notification channels
     */
    private function registerDefaultChannels(): void
    {
        $this->registerChannel('telegram', new TelegramNotificationChannel());
        
        // Set default channels for different types of notifications
        $this->defaultChannels = [
            'review' => ['telegram'],
            'admin' => ['telegram'],
            'alert' => ['telegram'],
        ];
    }

    /**
     * Register a notification channel
     *
     * @param string $name
     * @param NotificationChannelInterface $channel
     * @return self
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): self
    {
        $this->channels[$name] = $channel;
        return $this;
    }

    /**
     * Get a specific notification channel
     *
     * @param string $name
     * @return NotificationChannelInterface|null
     */
    public function getChannel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * Send notification via specific channel
     *
     * @param string $channelName
     * @param string $message
     * @param string|null $target
     * @param array $options
     * @return array
     */
    public function sendViaChannel(string $channelName, string $message, ?string $target = null, array $options = []): array
    {
        $channel = $this->getChannel($channelName);
        
        if (!$channel) {
            return [
                'success' => false,
                'error' => "Channel '{$channelName}' not found",
                'channel' => $channelName
            ];
        }

        if (!$channel->isAvailable()) {
            return [
                'success' => false,
                'error' => "Channel '{$channelName}' is not available",
                'channel' => $channelName
            ];
        }

        return $channel->send($message, $target, $options);
    }

    /**
     * Send notification via multiple channels
     *
     * @param array $channels Array of channel names or channel => target pairs
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendViaMultipleChannels(array $channels, string $message, array $options = []): array
    {
        $results = [];
        
        foreach ($channels as $channelName => $target) {
            // Handle both indexed and associative arrays
            if (is_numeric($channelName)) {
                $channelName = $target;
                $target = null;
            }
            
            $result = $this->sendViaChannel($channelName, $message, $target, $options);
            $results[$channelName] = $result;
        }

        return $results;
    }

    /**
     * Send review message using default channels
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendReviewMessage(string $message, array $options = []): array
    {
        $channels = $this->defaultChannels['review'] ?? ['telegram'];
        return $this->sendViaMultipleChannels($channels, $message, $options);
    }

    /**
     * Send admin message using default channels
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendAdminMessage(string $message, array $options = []): array
    {
        $channels = $this->defaultChannels['admin'] ?? ['telegram'];
        return $this->sendViaMultipleChannels($channels, $message, $options);
    }

    /**
     * Send alert message using default channels
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendAlertMessage(string $message, array $options = []): array
    {
        $channels = $this->defaultChannels['alert'] ?? ['telegram'];
        return $this->sendViaMultipleChannels($channels, $message, $options);
    }

    /**
     * Send Telegram message to specific channel
     *
     * @param string $message
     * @param string $channelName
     * @param array $options
     * @return array
     */
    public function sendTelegramMessage(string $message, string $channelName = 'review', array $options = []): array
    {
        $telegramChannel = $this->getChannel('telegram');
        
        if (!$telegramChannel) {
            return [
                'success' => false,
                'error' => 'Telegram channel not available',
                'channel' => 'telegram'
            ];
        }

        return $telegramChannel->send($message, $channelName, $options);
    }

    /**
     * Get all available channels
     *
     * @return array
     */
    public function getAvailableChannels(): array
    {
        $available = [];
        
        foreach ($this->channels as $name => $channel) {
            $available[$name] = [
                'name' => $name,
                'type' => $channel->getChannelName(),
                'available' => $channel->isAvailable()
            ];
        }

        return $available;
    }

    /**
     * Set default channels for a notification type
     *
     * @param string $type
     * @param array $channels
     * @return self
     */
    public function setDefaultChannels(string $type, array $channels): self
    {
        $this->defaultChannels[$type] = $channels;
        return $this;
    }

    /**
     * Log notification attempt
     *
     * @param string $type
     * @param string $message
     * @param array $results
     */
    private function logNotification(string $type, string $message, array $results): void
    {
        $successful = array_filter($results, fn($result) => $result['success'] ?? false);
        $failed = array_filter($results, fn($result) => !($result['success'] ?? false));

        Log::info("Notification sent", [
            'type' => $type,
            'message_length' => strlen($message),
            'successful_channels' => array_keys($successful),
            'failed_channels' => array_keys($failed),
            'total_channels' => count($results)
        ]);

        if (!empty($failed)) {
            Log::warning("Some notification channels failed", [
                'type' => $type,
                'failed_results' => $failed
            ]);
        }
    }
}
