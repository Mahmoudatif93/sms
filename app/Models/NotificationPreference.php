<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{

    protected $fillable = [
        'entity_type',
        'entity_id',
        'notification_type',
        'channel',
        'enabled',
        'settings',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'frequency',
        'last_sent_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'settings' => 'array',
        'quiet_hours_start' => 'datetime:H:i',
        'quiet_hours_end' => 'datetime:H:i',
        'last_sent_at' => 'datetime',
    ];

    // Entity types
    const ENTITY_USER = 'user';
    const ENTITY_WORKSPACE = 'workspace';
    const ENTITY_ORGANIZATION = 'organization';

    // Frequency options
    const FREQUENCY_IMMEDIATE = 'immediate';
    const FREQUENCY_HOURLY = 'hourly';
    const FREQUENCY_DAILY = 'daily';
    const FREQUENCY_WEEKLY = 'weekly';
    const FREQUENCY_NEVER = 'never';

    /**
     * Get the entity that owns this preference
     */
    public function entity()
    {
        return $this->morphTo();
    }

    /**
     * Scope for user preferences
     */
    public function scopeForUser($query, User $user)
    {
        return $query->where('entity_type', self::ENTITY_USER)
                    ->where('entity_id', $user->id);
    }

    /**
     * Scope for workspace preferences
     */
    public function scopeForWorkspace($query, Workspace $workspace)
    {
        return $query->where('entity_type', self::ENTITY_WORKSPACE)
                    ->where('entity_id', $workspace->id);
    }

    /**
     * Scope for organization preferences
     */
    public function scopeForOrganization($query, Organization $organization)
    {
        return $query->where('entity_type', self::ENTITY_ORGANIZATION)
                    ->where('entity_id', $organization->id);
    }

    /**
     * Scope by notification type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope by channel
     */
    public function scopeByChannel($query, string $channel)
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope for enabled preferences
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Check if notifications are allowed at current time
     */
    public function isAllowedNow(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // Check quiet hours
        if ($this->quiet_hours_start && $this->quiet_hours_end) {
            $now = now($this->timezone ?? config('app.timezone'));
            $start = $now->copy()->setTimeFromTimeString($this->quiet_hours_start->format('H:i'));
            $end = $now->copy()->setTimeFromTimeString($this->quiet_hours_end->format('H:i'));

            // Handle overnight quiet hours (e.g., 22:00 to 08:00)
            if ($start->gt($end)) {
                if ($now->gte($start) || $now->lte($end)) {
                    return false;
                }
            } else {
                if ($now->gte($start) && $now->lte($end)) {
                    return false;
                }
            }
        }

        // Check frequency limits
        if ($this->frequency !== self::FREQUENCY_IMMEDIATE && $this->last_sent_at) {
            $nextAllowedTime = $this->calculateNextAllowedTime();
            if (now()->lt($nextAllowedTime)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate next allowed time based on frequency
     */
    protected function calculateNextAllowedTime(): ?\Carbon\Carbon
    {
        if (!$this->last_sent_at) {
            return null;
        }

        switch ($this->frequency) {
            case self::FREQUENCY_HOURLY:
                return $this->last_sent_at->addHour();
            case self::FREQUENCY_DAILY:
                return $this->last_sent_at->addDay();
            case self::FREQUENCY_WEEKLY:
                return $this->last_sent_at->addWeek();
            case self::FREQUENCY_NEVER:
                return now()->addYears(100); // Effectively never
            default:
                return null;
        }
    }

    /**
     * Update last sent timestamp
     */
    public function markAsSent(): void
    {
        $this->update(['last_sent_at' => now()]);
    }

    /**
     * Get setting value
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->update(['settings' => $settings]);
    }

    /**
     * Get effective preferences for an entity and type
     */
    public static function getEffectivePreferences(string $entityType, int $entityId, string $notificationType): array
    {
        $preferences = self::where('entity_type', $entityType)
                          ->where('entity_id', $entityId)
                          ->where('notification_type', $notificationType)
                          ->get()
                          ->keyBy('channel');

        // Get default preferences for missing channels
        $defaultPreferences = config("notifications.default_preferences.{$notificationType}", []);

        $result = [];
        foreach (config('notifications.available_channels', []) as $channel) {
            if (isset($preferences[$channel])) {
                $result[$channel] = $preferences[$channel];
            } else {
                // Create default preference
                $result[$channel] = new self([
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'notification_type' => $notificationType,
                    'channel' => $channel,
                    'enabled' => $defaultPreferences[$channel]['enabled'] ?? true,
                    'frequency' => $defaultPreferences[$channel]['frequency'] ?? self::FREQUENCY_IMMEDIATE,
                    'settings' => $defaultPreferences[$channel]['settings'] ?? [],
                ]);
            }
        }

        return $result;
    }

    /**
     * Create or update preference
     */
    public static function setPreference(
        string $entityType,
        int $entityId,
        string $notificationType,
        string $channel,
        array $data
    ): self {
        return self::updateOrCreate([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'notification_type' => $notificationType,
            'channel' => $channel,
        ], $data);
    }

    /**
     * Bulk update preferences
     */
    public static function bulkUpdatePreferences(
        string $entityType,
        int $entityId,
        string $notificationType,
        array $preferences
    ): void {
        foreach ($preferences as $channel => $data) {
            self::setPreference($entityType, $entityId, $notificationType, $channel, $data);
        }
    }
}
