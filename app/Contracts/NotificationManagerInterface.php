<?php

namespace App\Contracts;

use App\Notifications\Core\NotificationMessage;
use App\Notifications\Core\NotificationResult;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Organization;

interface NotificationManagerInterface
{
    /**
     * Send a notification through specified channels
     *
     * @param NotificationMessage $message
     * @return array Array of NotificationResult objects
     */
    public function send(NotificationMessage $message): array;

    /**
     * Send notification to a user with their preferences
     *
     * @param User $user
     * @param string $type
     * @param string $content
     * @param array $data
     * @return array
     */
    public function sendToUser(User $user, string $type, string $content, array $data = []): array;

    /**
     * Send notification to a workspace
     *
     * @param Workspace $workspace
     * @param string $type
     * @param string $content
     * @param array $data
     * @return array
     */
    public function sendToWorkspace(Workspace $workspace, string $type, string $content, array $data = []): array;

    /**
     * Send notification to an organization
     *
     * @param Organization $organization
     * @param string $type
     * @param string $content
     * @param array $data
     * @return array
     */
    public function sendToOrganization(Organization $organization, string $type, string $content, array $data = []): array;

    /**
     * Send notification using a template
     *
     * @param string $templateId
     * @param array $recipients
     * @param array $variables
     * @param array $channels
     * @param array $options
     * @return array
     */
    public function sendFromTemplate(string $templateId, array $recipients, array $variables = [], array $channels = [], array $options = []): array;

    /**
     * Schedule a notification for later delivery
     *
     * @param NotificationMessage $message
     * @param \Carbon\Carbon $scheduledAt
     * @return string Scheduled notification ID
     */
    public function schedule(NotificationMessage $message, \Carbon\Carbon $scheduledAt): string;

    /**
     * Cancel a scheduled notification
     *
     * @param string $notificationId
     * @return bool
     */
    public function cancelScheduled(string $notificationId): bool;

    /**
     * Get notification status
     *
     * @param string $notificationId
     * @return array|null
     */
    public function getStatus(string $notificationId): ?array;

    /**
     * Register a notification channel
     *
     * @param string $name
     * @param NotificationChannelInterface $channel
     * @return self
     */
    public function registerChannel(string $name, NotificationChannelInterface $channel): self;

    /**
     * Get available channels
     *
     * @return array
     */
    public function getAvailableChannels(): array;

    /**
     * Set default channels for a notification type
     *
     * @param string $type
     * @param array $channels
     * @return self
     */
    public function setDefaultChannels(string $type, array $channels): self;

    /**
     * Get user's notification preferences
     *
     * @param User $user
     * @param string $type
     * @return array
     */
    public function getUserPreferences(User $user, string $type): array;

    /**
     * Update user's notification preferences
     *
     * @param User $user
     * @param string $type
     * @param array $preferences
     * @return bool
     */
    public function updateUserPreferences(User $user, string $type, array $preferences): bool;
}
