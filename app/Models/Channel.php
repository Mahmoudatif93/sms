<?php

namespace App\Models;

use App\Traits\RequiredActionManager;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property string $id
 * @property string $connector_id
 * @property string $workspace_id
 * @property string $name
 * @property string $status
 * @property string $platform
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connector $connector
 * @property-read WhatsappConfiguration|null $whatsappConfiguration
 * @property-read Workspace $workspace
 * @property string|null $default_workspace_id
 * @property-read Workspace|null $defaultWorkspace
 * @method static Builder<static>|Channel newModelQuery()
 * @method static Builder<static>|Channel newQuery()
 * @method static Builder<static>|Channel query()
 * @method static Builder<static>|Channel whereConnectorId($value)
 * @method static Builder<static>|Channel whereCreatedAt($value)
 * @method static Builder<static>|Channel whereId($value)
 * @method static Builder<static>|Channel whereName($value)
 * @method static Builder<static>|Channel wherePlatform($value)
 * @method static Builder<static>|Channel whereStatus($value)
 * @method static Builder<static>|Channel whereUpdatedAt($value)
 * @method static Builder<static>|Channel whereWorkspaceId($value)
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, Campaign> $campaigns
 * @property-read int|null $campaigns_count
 * @property-read MessengerConfiguration|null $messengerConfiguration
 * @property-read SmsConfiguration|null $smsConfiguration
 * @property-read TicketConfiguration|null $ticketConfiguration
 * @property-read Collection<int, WhatsappMessage> $whatsappMessages
 * @property-read int|null $whatsapp_messages_count
 * @property-read WhatsappPhoneNumber|null $whatsappPhoneNumber
 * @property-read WorkspaceChannel|null $pivot
 * @property-read Collection<int, Workspace> $workspaces
 * @property-read int|null $workspaces_count
 * @method static Builder<static>|Channel onlyTrashed()
 * @method static Builder<static>|Channel whereDeletedAt($value)
 * @method static Builder<static>|Channel withTrashed()
 * @method static Builder<static>|Channel withoutTrashed()
 * @mixin Eloquent
 */
class Channel extends Model
{

    use HasUuids;
    use SoftDeletes;
    use RequiredActionManager;

    const WHATSAPP_PLATFORM = 'whatsapp';
    const SMS_PLATFORM = 'sms';
    const LIVECHAT_PLATFORM = 'livechat';
    const TELEGRAM_PLATFORM = 'telegram';
    const TICKETING_PLATFORM = 'ticketing';
    const MESSENGER_PLATFORM = 'messenger';
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'failed';
    const STATUS_PENDING = "pending";
    const STATUS_EXPIRED = 'Expired';
    protected $fillable = [
        'id',
        'connector_id',
        'name',
        'status',
        'platform',
        'default_workspace_id', // ✅ new
    ];

    public function connector(): HasOne
    {
        return $this->hasOne(Connector::class, 'id', 'connector_id');
    }

    /**
     * Get the workspace that owns the channel.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_channel')
            ->using(WorkspaceChannel::class) // Specify the custom pivot model
            ->withTimestamps(); // Include timestamps
    }


    public function whatsappConfiguration(): HasOneThrough
    {
        return $this->hasOneThrough(
            WhatsappConfiguration::class,
            Connector::class,
            'id',           // Foreign key on connectors table
            'connector_id', // Foreign key on whatsapp_configurations table
            'connector_id', // Local key on channels table
            'id'            // Local key on connectors table
        );
    }

    public function smsConfiguration()
    {
        return $this->hasOne(SmsConfiguration::class, 'connector_id', 'connector_id');
    }

    public function ticketConfiguration()
    {
        return $this->hasOne(TicketConfiguration::class, 'connector_id', 'connector_id');
    }

    /**
     * Get the primary WhatsApp phone number associated with the channel.
     */
    public function whatsappPhoneNumber(): HasOneThrough
    {
        return $this->hasOneThrough(
            WhatsappPhoneNumber::class,
            WhatsappConfiguration::class,
            'connector_id',                      // Foreign key on whatsapp_configurations
            'id',                                // Foreign key on whatsapp_phone_numbers
            'connector_id',                      // Local key on channels
            'primary_whatsapp_phone_number_id'   // Local key on whatsapp_configurations
        );
    }

    /**
     * Get all WhatsApp messages associated with the channel.
     */
    public function whatsappMessages(): HasManyThrough
    {
        return $this->hasManyThrough(
            WhatsappMessage::class,
            WhatsappPhoneNumber::class,
            'id',                       // Foreign key on whatsapp_phone_numbers
            'whatsapp_phone_number_id', // Foreign key on whatsapp_messages
            'id',                       // Local key on channels
            'id'                        // Local key on whatsapp_phone_numbers
        );
    }

    /**
     * Get all campaigns directly associated with the channel via `channel_id`.
     *
     * @return HasMany
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class, 'channel_id');
    }

    /**
     * Get the Messenger configuration through the connector.
     */
    public function messengerConfiguration(): HasOneThrough
    {
        return $this->hasOneThrough(
            MessengerConfiguration::class,
            Connector::class,
            'id',           // Foreign key on the connectors table
            'connector_id', // Foreign key on the messenger_configurations table
            'connector_id', // Local key on the channels table
            'id'            // Local key on the connectors table
        );
    }

    /**
     * Get the required actions for the channel.
     *
     * @return MorphMany
     */
    public function requiredActions(): MorphMany
    {
        return $this->morphMany(RequiredAction::class, 'actionable');
    }

    /**
     * The default workspace for this channel (optional).
     */
    public function defaultWorkspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'default_workspace_id');
    }

    /**
     * @return Organization|null
     */
    /**public function organization(): ?Organization
    {
        return $this->defaultWorkspace?->organization;
    }*/


    public function organization(): ?Organization
    {
        // 1. Try default workspace
        if ($this->defaultWorkspace) {
            return $this->defaultWorkspace->organization;
        }

        // 2. Try connector's workspace
        if ($this->connector && $this->connector->workspace) {
            return $this->connector->workspace->organization;
        }

        // 3. Nothing found
        return null;
    }

    /**
     * Get the chatbot settings for this channel.
     */
    public function chatbotSettings(): HasOne
    {
        return $this->hasOne(ChatbotSettings::class);
    }

    /**
     * Get the chatbot knowledge base entries for this channel.
     */
    public function chatbotKnowledge(): HasMany
    {
        return $this->hasMany(ChatbotKnowledgeBase::class);
    }

    /**
     * Check if chatbot is enabled for this channel.
     */
    public function isChatbotEnabled(): bool
    {
        return $this->chatbotSettings?->is_enabled ?? false;
    }
}
