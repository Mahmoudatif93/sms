<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannelInterface;
use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use Illuminate\Support\Facades\Log;
use Exception;

class ChannelManager
{
    protected array $channels = [];
    protected array $defaultChannels = [];
    protected array $channelConfigurations = [];

    public function __construct()
    {
        $this->loadDefaultChannels();
        $this->loadConfigurations();
    }

    /**
     * Register a notification channel
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): self
    {
        $this->channels[$name] = $channel;
        return $this;
    }

    /**
     * Get a specific channel
     */
    public function getChannel(string $name): ?NotificationChannelInterface
    {
        return $this->channels[$name] ?? null;
    }

    /**
     * Get all registered channels
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Get available channels (configured and working)
     */
    public function getAvailableChannels(): array
    {
        $available = [];
        
        foreach ($this->channels as $name => $channel) {
            if ($channel->isAvailable()) {
                $available[$name] = [
                    'name' => $name,
                    'type' => $channel->getChannelName(),
                    'configuration' => $channel->getConfiguration(),
                    'limits' => $channel->getLimits(),
                    'supports_delivery_confirmation' => $channel->supportsDeliveryConfirmation(),
                ];
            }
        }

        return $available;
    }

    /**
     * Send message through a specific channel
     */
    public function sendViaChannel(string $channelName, NotificationMessage $message, array $options = []): NotificationResult
    {
        $channel = $this->getChannel($channelName);
        
        if (!$channel) {
            return NotificationResult::failure(
                $channelName,
                "Channel '{$channelName}' not found"
            );
        }

        if (!$channel->isAvailable()) {
            return NotificationResult::failure(
                $channelName,
                "Channel '{$channelName}' is not available"
            );
        }

        if (!$channel->validateMessage($message)) {
            return NotificationResult::failure(
                $channelName,
                "Message validation failed for channel '{$channelName}'"
            );
        }

        try {
            // Create channel-specific message if channel contents are available
            $channelMessage = $this->createChannelSpecificMessage($message, $channelName);

            $result = $channel->send($channelMessage, $options);

            Log::info("Message sent via channel", [
                'channel' => $channelName,
                'message_id' => $message->getId(),
                'success' => $result->isSuccess(),
                'external_id' => $result->getExternalId(),
                'has_channel_content' => $channelMessage !== $message
            ]);

            return $result;
            
        } catch (Exception $e) {
            Log::error("Channel send failed", [
                'channel' => $channelName,
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return NotificationResult::fromException($channelName, $e);
        }
    }

    /**
     * Send message through multiple channels
     */
    public function sendViaMultipleChannels(NotificationMessage $message, array $channels = []): array
    {
        if (empty($channels)) {
            $channels = $message->getChannels();
        }

        if (empty($channels)) {
            $channels = $this->getDefaultChannelsForType($message->getType());
        }

        $results = [];
        
        foreach ($channels as $channelName => $options) {
            // Handle both indexed and associative arrays
            if (is_numeric($channelName)) {
                $channelName = $options;
                $options = [];
            }

            $result = $this->sendViaChannel($channelName, $message, $options);
            $results[$channelName] = $result;
        }

        return $results;
    }

    /**
     * Set default channels for a notification type
     */
    public function setDefaultChannels(string $type, array $channels): self
    {
        $this->defaultChannels[$type] = $channels;
        return $this;
    }

    /**
     * Get default channels for a notification type
     */
    public function getDefaultChannelsForType(string $type): array
    {
        return $this->defaultChannels[$type] ?? 
               config("notifications.default_channels.{$type}", []) ??
               ['sms']; // fallback
    }

    /**
     * Check if a channel is available
     */
    public function isChannelAvailable(string $channelName): bool
    {
        $channel = $this->getChannel($channelName);
        return $channel && $channel->isAvailable();
    }

    /**
     * Create channel-specific message with appropriate content
     */
    private function createChannelSpecificMessage(NotificationMessage $message, string $channelName): NotificationMessage
    {
        $data = $message->getData();

        // Check if we have channel-specific content
        if (!isset($data['channel_contents'][$channelName])) {
            return $message; // Return original message if no channel-specific content
        }

        $channelContent = $data['channel_contents'][$channelName];

        // Create a copy of the original message and update content
        $channelMessage = clone $message;

        // Update with channel-specific content
        $channelMessage->setTitle($channelContent['title'])
                      ->setContent($channelContent['body'])
                      ->setChannels([$channelName => []]);

        // Add channel-specific template if available
        if (!empty($channelContent['template'])) {
            $channelMessage->setData(array_merge($message->getData(), [
                'email_template' => $channelContent['template']
            ]));
        } else {
            $channelMessage->setData($message->getData());
        }

        return $channelMessage;
    }

    /**
     * Get channel statistics
     */
    public function getChannelStatistics(string $channelName, int $days = 7): array
    {
        // This would typically query the notification logs
        // For now, return basic structure
        return [
            'channel' => $channelName,
            'period_days' => $days,
            'total_sent' => 0,
            'total_delivered' => 0,
            'total_failed' => 0,
            'success_rate' => 0,
            'average_delivery_time' => 0,
        ];
    }

    /**
     * Get all channels statistics
     */
    public function getAllChannelsStatistics(int $days = 7): array
    {
        $stats = [];
        
        foreach (array_keys($this->channels) as $channelName) {
            $stats[$channelName] = $this->getChannelStatistics($channelName, $days);
        }

        return $stats;
    }

    /**
     * Test channel connectivity
     */
    public function testChannel(string $channelName): array
    {
        $channel = $this->getChannel($channelName);
        
        if (!$channel) {
            return [
                'success' => false,
                'error' => "Channel '{$channelName}' not found",
                'channel' => $channelName
            ];
        }

        try {
            $isAvailable = $channel->isAvailable();
            $configuration = $channel->getConfiguration();
            
            return [
                'success' => $isAvailable,
                'channel' => $channelName,
                'available' => $isAvailable,
                'configuration' => $configuration,
                'limits' => $channel->getLimits(),
                'supports_delivery_confirmation' => $channel->supportsDeliveryConfirmation(),
                'tested_at' => now()->toISOString(),
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'channel' => $channelName,
                'tested_at' => now()->toISOString(),
            ];
        }
    }

    /**
     * Test all channels
     */
    public function testAllChannels(): array
    {
        $results = [];
        
        foreach (array_keys($this->channels) as $channelName) {
            $results[$channelName] = $this->testChannel($channelName);
        }

        return $results;
    }

    /**
     * Load default channels from configuration
     */
    protected function loadDefaultChannels(): void
    {
        $this->defaultChannels = config('notifications.default_channels', []);
    }

    /**
     * Load channel configurations
     */
    protected function loadConfigurations(): void
    {
        $this->channelConfigurations = config('notifications.channels', []);
    }

    /**
     * Get configuration for a channel
     */
    public function getChannelConfiguration(string $channelName): array
    {
        return $this->channelConfigurations[$channelName] ?? [];
    }

    /**
     * Update channel configuration
     */
    public function updateChannelConfiguration(string $channelName, array $configuration): void
    {
        $this->channelConfigurations[$channelName] = array_merge(
            $this->channelConfigurations[$channelName] ?? [],
            $configuration
        );
    }

    /**
     * Get channels that support delivery confirmation
     */
    public function getChannelsWithDeliveryConfirmation(): array
    {
        $channels = [];
        
        foreach ($this->channels as $name => $channel) {
            if ($channel->supportsDeliveryConfirmation()) {
                $channels[] = $name;
            }
        }

        return $channels;
    }

    /**
     * Get best channel for a message type based on success rates
     */
    public function getBestChannelForType(string $type): ?string
    {
        $availableChannels = $this->getDefaultChannelsForType($type);
        
        if (empty($availableChannels)) {
            return null;
        }

        // For now, return the first available channel
        // In a real implementation, this would check success rates
        foreach ($availableChannels as $channel) {
            if ($this->isChannelAvailable($channel)) {
                return $channel;
            }
        }

        return null;
    }
}
