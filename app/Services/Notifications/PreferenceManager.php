<?php

namespace App\Services\Notifications;

use App\Contracts\NotificationPreferenceInterface;
use App\Models\User;
use App\Models\Workspace;
use App\Models\Organization;
use App\Models\NotificationPreference;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PreferenceManager implements NotificationPreferenceInterface
{
    protected array $defaultPreferences;
    protected array $availableTypes;
    protected array $availableChannels;

    public function __construct()
    {
        $this->defaultPreferences = config('notifications.default_preferences', []);
        $this->availableTypes = config('notifications.available_types', []);
        $this->availableChannels = config('notifications.available_channels', []);
    }

    /**
     * Get user notification preferences
     */
    public function getUserPreferences(User $user, string $type): array
    {
        $cacheKey = "user_preferences_{$user->id}_{$type}";
        
        return Cache::remember($cacheKey, 3600, function () use ($user, $type) {
            return NotificationPreference::getEffectivePreferences(
                NotificationPreference::ENTITY_USER,
                $user->id,
                $type
            );
        });
    }

    /**
     * Set user notification preferences
     */
    public function setUserPreferences(User $user, string $type, array $preferences): bool
    {
        try {
            NotificationPreference::bulkUpdatePreferences(
                NotificationPreference::ENTITY_USER,
                $user->id,
                $type,
                $preferences
            );

            // Clear cache
            $cacheKey = "user_preferences_{$user->id}_{$type}";
            Cache::forget($cacheKey);

            Log::info("User preferences updated", [
                'user_id' => $user->id,
                'type' => $type,
                'preferences' => $preferences
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update user preferences", [
                'user_id' => $user->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get workspace notification preferences
     */
    public function getWorkspacePreferences(Workspace $workspace, string $type): array
    {
        $cacheKey = "workspace_preferences_{$workspace->id}_{$type}";
        
        return Cache::remember($cacheKey, 3600, function () use ($workspace, $type) {
            return NotificationPreference::getEffectivePreferences(
                NotificationPreference::ENTITY_WORKSPACE,
                $workspace->id,
                $type
            );
        });
    }

    /**
     * Set workspace notification preferences
     */
    public function setWorkspacePreferences(Workspace $workspace, string $type, array $preferences): bool
    {
        try {
            NotificationPreference::bulkUpdatePreferences(
                NotificationPreference::ENTITY_WORKSPACE,
                $workspace->id,
                $type,
                $preferences
            );

            // Clear cache
            $cacheKey = "workspace_preferences_{$workspace->id}_{$type}";
            Cache::forget($cacheKey);

            Log::info("Workspace preferences updated", [
                'workspace_id' => $workspace->id,
                'type' => $type,
                'preferences' => $preferences
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update workspace preferences", [
                'workspace_id' => $workspace->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get organization notification preferences
     */
    public function getOrganizationPreferences(Organization $organization, string $type): array
    {
        $cacheKey = "organization_preferences_{$organization->id}_{$type}";
        
        return Cache::remember($cacheKey, 3600, function () use ($organization, $type) {
            return NotificationPreference::getEffectivePreferences(
                NotificationPreference::ENTITY_ORGANIZATION,
                $organization->id,
                $type
            );
        });
    }

    /**
     * Set organization notification preferences
     */
    public function setOrganizationPreferences(Organization $organization, string $type, array $preferences): bool
    {
        try {
            NotificationPreference::bulkUpdatePreferences(
                NotificationPreference::ENTITY_ORGANIZATION,
                $organization->id,
                $type,
                $preferences
            );

            // Clear cache
            $cacheKey = "organization_preferences_{$organization->id}_{$type}";
            Cache::forget($cacheKey);

            Log::info("Organization preferences updated", [
                'organization_id' => $organization->id,
                'type' => $type,
                'preferences' => $preferences
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update organization preferences", [
                'organization_id' => $organization->id,
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get effective preferences for a user (considering hierarchy)
     */
    public function getEffectivePreferences(User $user, string $type): array
    {
        $cacheKey = "effective_preferences_{$user->id}_{$type}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user, $type) {
            // Start with default preferences
            $preferences = $this->getDefaultPreferences($type);
            
            // Apply organization preferences if user belongs to one
            if ($user->organization_id) {
                $organization = Organization::find($user->organization_id);
                if ($organization) {
                    $orgPreferences = $this->getOrganizationPreferences($organization, $type);
                    $preferences = $this->mergePreferences($preferences, $orgPreferences);
                }
            }
            
            // Apply workspace preferences if user belongs to one
            if ($user->workspace_id) {
                $workspace = Workspace::find($user->workspace_id);
                if ($workspace) {
                    $workspacePreferences = $this->getWorkspacePreferences($workspace, $type);
                    $preferences = $this->mergePreferences($preferences, $workspacePreferences);
                }
            }
            
            // Apply user-specific preferences (highest priority)
            $userPreferences = $this->getUserPreferences($user, $type);
            $preferences = $this->mergePreferences($preferences, $userPreferences);
            
            return $preferences;
        });
    }

    /**
     * Check if user has opted out of a notification type
     */
    public function hasOptedOut(User $user, string $type, string $channel): bool
    {
        $preferences = $this->getEffectivePreferences($user, $type);
        
        return isset($preferences[$channel]) && 
               (!$preferences[$channel]->enabled || 
                $preferences[$channel]->frequency === NotificationPreference::FREQUENCY_NEVER);
    }

    /**
     * Opt user out of a notification type/channel
     */
    public function optOut(User $user, string $type, string $channel): bool
    {
        return $this->updateUserChannelPreference($user, $type, $channel, [
            'enabled' => false,
            'frequency' => NotificationPreference::FREQUENCY_NEVER
        ]);
    }

    /**
     * Opt user back in to a notification type/channel
     */
    public function optIn(User $user, string $type, string $channel): bool
    {
        return $this->updateUserChannelPreference($user, $type, $channel, [
            'enabled' => true,
            'frequency' => NotificationPreference::FREQUENCY_IMMEDIATE
        ]);
    }

    /**
     * Get default preferences for a notification type
     */
    public function getDefaultPreferences(string $type): array
    {
        return $this->defaultPreferences[$type] ?? [];
    }

    /**
     * Set default preferences for a notification type
     */
    public function setDefaultPreferences(string $type, array $preferences): bool
    {
        try {
            // This would typically update configuration or database
            // For now, we'll just update the in-memory array
            $this->defaultPreferences[$type] = $preferences;
            
            Log::info("Default preferences updated", [
                'type' => $type,
                'preferences' => $preferences
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update default preferences", [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get all available notification types
     */
    public function getAvailableTypes(): array
    {
        return $this->availableTypes;
    }

    /**
     * Get all available channels
     */
    public function getAvailableChannels(): array
    {
        return $this->availableChannels;
    }

    /**
     * Update user channel preference
     */
    protected function updateUserChannelPreference(User $user, string $type, string $channel, array $data): bool
    {
        try {
            NotificationPreference::setPreference(
                NotificationPreference::ENTITY_USER,
                $user->id,
                $type,
                $channel,
                $data
            );

            // Clear relevant caches
            Cache::forget("user_preferences_{$user->id}_{$type}");
            Cache::forget("effective_preferences_{$user->id}_{$type}");

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to update user channel preference", [
                'user_id' => $user->id,
                'type' => $type,
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Merge preferences with priority
     */
    protected function mergePreferences(array $base, array $override): array
    {
        foreach ($override as $channel => $preference) {
            if ($preference instanceof NotificationPreference && $preference->exists) {
                $base[$channel] = $preference;
            }
        }

        return $base;
    }
}
