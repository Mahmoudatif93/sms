<?php

namespace App\Contracts;

use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;

interface NotificationChannelInterface
{
    /**
     * Send a notification message
     *
     * @param NotificationMessage $message The notification message object
     * @param array $options Additional options for the notification
     * @return NotificationResult Response with success status and data
     */
    public function send(NotificationMessage $message, array $options = []): NotificationResult;

    /**
     * Check if the channel is available and configured
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Get the channel name/type
     *
     * @return string
     */
    public function getChannelName(): string;

    /**
     * Get channel configuration
     *
     * @return array
     */
    public function getConfiguration(): array;

    /**
     * Validate message for this channel
     *
     * @param NotificationMessage $message
     * @return bool
     */
    public function validateMessage(NotificationMessage $message): bool;

    /**
     * Get channel limits (rate limiting, message size, etc.)
     *
     * @return array
     */
    public function getLimits(): array;

    /**
     * Check if channel supports delivery confirmation
     *
     * @return bool
     */
    public function supportsDeliveryConfirmation(): bool;
}
