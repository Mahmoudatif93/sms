<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationChannelInterface;
use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramNotificationChannel implements NotificationChannelInterface
{
    private string $botToken;
    private array $channels;
    private string $apiUrl;
    private int $timeout;
    private array $configuration;

    public function __construct()
    {
        // Use unified configuration from notifications.php
        $telegramConfig = config('notifications.available_channels.telegram', []);

        $this->botToken = $telegramConfig['bot_token'] ?? '';
        $this->channels = $telegramConfig['channels'] ?? [];
        $this->apiUrl = $telegramConfig['api_url'] ?? 'https://api.telegram.org/bot';
        $this->timeout = $telegramConfig['timeout'] ?? 10;
        $this->configuration = $telegramConfig['config'] ?? [];
    }

    /**
     * Send a notification message via Telegram
     *
     * @param NotificationMessage $message The notification message object
     * @param array $options Additional options (parse_mode, disable_web_page_preview, etc.)
     * @return NotificationResult Response with success status and data
     */
    public function send(NotificationMessage $message, array $options = []): NotificationResult
    {
        try {
            if (!$this->isAvailable()) {
                Log::warning('Telegram bot token is not configured. Notification not sent.');
                return NotificationResult::failure(
                    'telegram',
                    'Telegram bot token is not configured'
                );
            }

            $recipients = $this->extractTelegramRecipients($message);
            if (empty($recipients)) {
                return NotificationResult::failure(
                    'telegram',
                    'No valid Telegram recipients found'
                );
            }

            $content = $this->prepareContent($message, $options);

            $results = [];
            foreach ($recipients as $recipient) {

                try {
                    $chatId = $recipient['chat_id'];
                    $url = $this->apiUrl . $this->botToken . '/sendMessage';

                    $payload = array_merge([
                        'chat_id' => $chatId,
                        'text' => $content,
                        'parse_mode' => $options['parse_mode'] ?? 'HTML',
                    ], $options);

                    $response = Http::timeout($this->timeout)->post($url, $payload);

                    if ($response->successful()) {
                        $responseData = $response->json();
                        $telegramMessageId = $responseData['result']['message_id'] ?? null;

                        $results[] = NotificationResult::success(
                            'telegram',
                            $chatId,
                            $message->getId() . '_' . $telegramMessageId
                        )->setExternalId($telegramMessageId)
                         ->addData('telegram_response', $responseData);

                        Log::info('Telegram message sent successfully', [
                            'message_id' => $message->getId(),
                            'chat_id' => $chatId,
                            'telegram_message_id' => $telegramMessageId
                        ]);

                    } else {
                        $error = $response->json();
                        $errorMessage = $error['description'] ?? 'Unknown Telegram API error';

                        $results[] = NotificationResult::failure('telegram', $errorMessage, $chatId)
                            ->addData('status_code', $response->status())
                            ->addData('telegram_error', $error);

                        Log::error('Failed to send Telegram message', [
                            'message_id' => $message->getId(),
                            'bot_id' => $this->botToken,
                            'chat_id' => $chatId,
                            'error' => $error,
                            'status' => $response->status()
                        ]);
                    }

                } catch (Exception $e) {
                    $results[] = NotificationResult::fromException('telegram', $e, $recipient['chat_id']);

                    Log::error("Telegram send failed for recipient", [
                        'message_id' => $message->getId(),
                        'chat_id' => $recipient['chat_id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Return the first result for single recipient, or success if all succeeded
            if (count($results) === 1) {
                return $results[0];
            }

            $successCount = count(array_filter($results, fn($r) => $r->isSuccess()));
            $totalCount = count($results);

            if ($successCount === $totalCount) {
                return NotificationResult::success('telegram', null, $message->getId())
                    ->addData('total_recipients', $totalCount)
                    ->addData('successful_sends', $successCount);
            } else {
                return NotificationResult::failure(
                    'telegram',
                    "Partial failure: {$successCount}/{$totalCount} messages sent"
                )->addData('total_recipients', $totalCount)
                 ->addData('successful_sends', $successCount)
                 ->addData('failed_sends', $totalCount - $successCount);
            }

        } catch (Exception $e) {
            Log::error('Telegram channel error', [
                'message_id' => $message->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return NotificationResult::fromException('telegram', $e);
        }
    }

    /**
     * Check if Telegram is available and configured
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return !empty($this->botToken) && $this->botToken !== 'your_telegram_bot_token';
    }

    /**
     * Get the channel name
     *
     * @return string
     */
    public function getChannelName(): string
    {
        return 'telegram';
    }

    /**
     * Get channel configuration
     *
     * @return array
     */
    public function getConfiguration(): array
    {
        return [
            'enabled' => $this->isAvailable(),
            'bot_token' => !empty($this->botToken) ? 'configured' : 'not_configured',
            'available_channels' => array_keys($this->channels),
            'api_url' => $this->apiUrl,
            'timeout' => $this->timeout,
            'max_message_length' => $this->configuration['max_message_length'] ?? 4096,
        ];
    }

    /**
     * Validate message for Telegram channel
     *
     * @param NotificationMessage $message
     * @return bool
     */
    public function validateMessage(NotificationMessage $message): bool
    {
        $content = $message->getContent();
        $maxLength = $this->configuration['max_message_length'] ?? 4096;

        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            Log::warning("Telegram message too long", [
                'message_id' => $message->getId(),
                'length' => mb_strlen($content, 'UTF-8'),
                'max_length' => $maxLength
            ]);
            return false;
        }

        $recipients = $this->extractTelegramRecipients($message);
        if (empty($recipients)) {
            Log::warning("No valid Telegram recipients found", [
                'message_id' => $message->getId(),
                'recipients' => $message->getRecipients()
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get channel limits
     *
     * @return array
     */
    public function getLimits(): array
    {
        return [
            'max_message_length' => $this->configuration['max_message_length'] ?? 4096,
            'max_recipients_per_request' => $this->configuration['max_recipients'] ?? 10,
            'rate_limit_per_minute' => $this->configuration['rate_limit_per_minute'] ?? 30,
            'rate_limit_per_second' => $this->configuration['rate_limit_per_second'] ?? 1,
        ];
    }

    /**
     * Check if channel supports delivery confirmation
     *
     * @return bool
     */
    public function supportsDeliveryConfirmation(): bool
    {
        return $this->configuration['supports_delivery_confirmation'] ?? false;
    }

    /**
     * Get available channel names
     *
     * @return array
     */
    public function getAvailableChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Resolve chat ID from channel name or return the provided chat ID
     *
     * @param string|null $channel
     * @return string|null
     */
    private function resolveChatId(?string $channel): ?string
    {
        if (empty($channel)) {
            // Return default channel if available
            return $this->channels['review'] ?? null;
        }

        // If it's a direct chat ID (starts with - or is numeric)
        if (is_numeric($channel) || str_starts_with($channel, '-')) {
            return $channel;
        }

        // If it's a channel name, resolve from config
        return $this->channels[$channel] ?? null;
    }

    /**
     * Send message to a specific predefined channel
     *
     * @param string $channelName
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendToChannel(string $channelName, string $message, array $options = []): array
    {
        return $this->send($message, $channelName, $options);
    }

    /**
     * Send review message to the review channel
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendReviewMessage(string $message, array $options = []): array
    {
        return $this->sendToChannel('review', $message, $options);
    }

    /**
     * Send admin message to the admin channel
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendAdminMessage(string $message, array $options = []): array
    {
        return $this->sendToChannel('admin', $message, $options);
    }

    /**
     * Send alert message to the alerts channel
     *
     * @param string $message
     * @param array $options
     * @return array
     */
    public function sendAlertMessage(string $message, array $options = []): array
    {
        return $this->sendToChannel('alerts', $message, $options);
    }

    /**
     * Extract Telegram recipients from message
     *
     * @param NotificationMessage $message
     * @return array
     */
    protected function extractTelegramRecipients(NotificationMessage $message): array
    {
        $recipients = [];

        foreach ($message->getRecipients() as $recipient) {
            switch ($recipient['type']) {
                case 'telegram_chat':
                    $recipients[] = [
                        'chat_id' => $recipient['identifier'],
                        'type' => 'direct',
                        'metadata' => $recipient['metadata'] ?? []
                    ];
                    break;

                case 'telegram':
                    $chatId = $this->resolveChatId($recipient['identifier']);
                    if ($chatId) {
                        $recipients[] = [
                            'chat_id' => $chatId,
                            'type' => 'channel',
                            'channel_name' => $recipient['identifier'],
                            'metadata' => $recipient['metadata'] ?? []
                        ];
                    }
                    break;

                case 'user':
                    // For users, we might have their Telegram chat ID stored
                    $user = \App\Models\User::find($recipient['identifier']);
                    if ($user && isset($user->telegram_chat_id)) {
                        $recipients[] = [
                            'chat_id' => $user->telegram_chat_id,
                            'type' => 'user',
                            'user_id' => $user->id,
                            'metadata' => $recipient['metadata'] ?? []
                        ];
                    }
                    break;
            }
        }

        // If no specific recipients, use default channels based on message type
        if (empty($recipients)) {
            $defaultChannel = $this->getDefaultChannelForType($message->getType());
            if ($defaultChannel) {
                $chatId = $this->resolveChatId($defaultChannel);
                if ($chatId) {
                    $recipients[] = [
                        'chat_id' => $chatId,
                        'type' => 'default',
                        'channel_name' => $defaultChannel,
                        'metadata' => []
                    ];
                }
            }
        }

        return $recipients;
    }

    /**
     * Get default channel for message type
     *
     * @param string $type
     * @return string|null
     */
    protected function getDefaultChannelForType(string $type): ?string
    {
        $mapping = [
            'review' => 'review',
            'admin' => 'admin',
            'alert' => 'alerts',
            'error' => 'alerts',
            'warning' => 'alerts',
        ];

        return $mapping[$type] ?? 'admin';
    }

    /**
     * Prepare content for Telegram
     *
     * @param NotificationMessage $message
     * @param array $options
     * @return string
     */
    protected function prepareContent(NotificationMessage $message, array $options): string
    {
        $content = $message->getContent();
        $maxLength = $this->configuration['max_message_length'] ?? 4096;

        // Add title if available
        if ($message->getTitle()) {
            // $content = "<b>{$message->getTitle()}</b>\n\n{$content}";
        }

        // Add metadata if configured
        if ($options['include_metadata'] ?? true) {
            $metadata = $this->formatMetadata($message);
            if ($metadata) {
                $content .= "\n\n{$metadata}";
            }
        }

        // Add timestamp
        if ($options['include_timestamp'] ?? true) {
            $content .= "\n\n⏰ <b>الوقت:</b> " . now()->format('Y-m-d H:i:s');
        }

        // Truncate if too long
        if (mb_strlen($content, 'UTF-8') > $maxLength) {
            $content = mb_substr($content, 0, $maxLength - 10, 'UTF-8') . "\n\n[مقطوع...]";
        }

        return $content;
    }

    /**
     * Format metadata for display
     *
     * @param NotificationMessage $message
     * @return string
     */
    protected function formatMetadata(NotificationMessage $message): string
    {
        $metadata = $message->getMetadata();
        $formatted = [];

        foreach ($metadata as $key => $value) {
            if (is_scalar($value)) {
                $formatted[] = "<b>{$key}:</b> {$value}";
            }
        }

        return implode("\n", $formatted);
    }
}
