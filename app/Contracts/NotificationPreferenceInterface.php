<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\Workspace;
use App\Models\Organization;

interface NotificationPreferenceInterface
{
    /**
     * Get user notification preferences
     *
     * @param User $user
     * @param string $type
     * @return array
     */
    public function getUserPreferences(User $user, string $type): array;

    /**
     * Set user notification preferences
     *
     * @param User $user
     * @param string $type
     * @param array $preferences
     * @return bool
     */
    public function setUserPreferences(User $user, string $type, array $preferences): bool;

    /**
     * Get workspace notification preferences
     *
     * @param Workspace $workspace
     * @param string $type
     * @return array
     */
    public function getWorkspacePreferences(Workspace $workspace, string $type): array;

    /**
     * Set workspace notification preferences
     *
     * @param Workspace $workspace
     * @param string $type
     * @param array $preferences
     * @return bool
     */
    public function setWorkspacePreferences(Workspace $workspace, string $type, array $preferences): bool;

    /**
     * Get organization notification preferences
     *
     * @param Organization $organization
     * @param string $type
     * @return array
     */
    public function getOrganizationPreferences(Organization $organization, string $type): array;

    /**
     * Set organization notification preferences
     *
     * @param Organization $organization
     * @param string $type
     * @param array $preferences
     * @return bool
     */
    public function setOrganizationPreferences(Organization $organization, string $type, array $preferences): bool;

    /**
     * Get effective preferences for a user (considering hierarchy)
     *
     * @param User $user
     * @param string $type
     * @return array
     */
    public function getEffectivePreferences(User $user, string $type): array;

    /**
     * Check if user has opted out of a notification type
     *
     * @param User $user
     * @param string $type
     * @param string $channel
     * @return bool
     */
    public function hasOptedOut(User $user, string $type, string $channel): bool;

    /**
     * Opt user out of a notification type/channel
     *
     * @param User $user
     * @param string $type
     * @param string $channel
     * @return bool
     */
    public function optOut(User $user, string $type, string $channel): bool;

    /**
     * Opt user back in to a notification type/channel
     *
     * @param User $user
     * @param string $type
     * @param string $channel
     * @return bool
     */
    public function optIn(User $user, string $type, string $channel): bool;

    /**
     * Get default preferences for a notification type
     *
     * @param string $type
     * @return array
     */
    public function getDefaultPreferences(string $type): array;

    /**
     * Set default preferences for a notification type
     *
     * @param string $type
     * @param array $preferences
     * @return bool
     */
    public function setDefaultPreferences(string $type, array $preferences): bool;

    /**
     * Get all available notification types
     *
     * @return array
     */
    public function getAvailableTypes(): array;

    /**
     * Get all available channels
     *
     * @return array
     */
    public function getAvailableChannels(): array;
}
