<?php

namespace App\Models;

use Carbon\Carbon;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Str;

/**
 * Class Conversation
 *
 * Represents a customer conversation across different platforms.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $platform The platform of the conversation (e.g., WhatsApp, SMS, LiveChat)
 * @property string $channel_id Foreign Key - The channel the conversation belongs to
 * @property string $contact_id Foreign Key - The contact involved in the conversation
 * @property string $status The status of the conversation (e.g., open, closed, pending)
 * @property string|null $detected_language The detected language of the conversation
 * @property int|null $created_at Timestamp when the conversation was created
 * @property int|null $updated_at Timestamp when the conversation was last updated
 * @property int|null $deleted_at Timestamp when the conversation was soft-deleted (null if not deleted)
 *
 * @property-read Channel $channel The channel associated with this conversation
 * @property-read ContactEntity $contact The contact associated with this conversation
 * @property-read \Illuminate\Database\Eloquent\Collection|PreChatFormFieldResponse[] $preChatFormFieldResponse The pre-chat form responses associated with this conversation
 *
 * @method static Builder|Conversation newModelQuery()
 * @method static Builder|Conversation newQuery()
 * @method static Builder|Conversation query()
 * @method static Builder|Conversation whereId($value)
 * @method static Builder|Conversation wherePlatform($value)
 * @method static Builder|Conversation whereChannelId($value)
 * @method static Builder|Conversation whereContactId($value)
 * @method static Builder|Conversation whereStatus($value)
 * @method static Builder|Conversation whereCreatedAt($value)
 * @method static Builder|Conversation whereUpdatedAt($value)
 * @method static Builder|Conversation onlyTrashed()
 * @method static Builder|Conversation withTrashed()
 * @method static Builder|Conversation withoutTrashed()
 *
 * @mixin Eloquent
 */
class Conversation extends Model
{
    use HasUuids, SoftDeletes;
    const STATUS_PENDING = 'pending';
    const STATUS_OPEN = 'open';
    const STATUS_ACTIVE = 'active';
    const STATUS_WAITING = 'waiting';
    const STATUS_ENDED = 'ended';
    const STATUS_MISSED = 'missed';
    const STATUS_CLOSED = 'closed';
    const STATUS_ARCHIVED = 'archived';


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conversations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['platform', 'channel_id', 'contact_id', 'status', 'workspace_id', 'last_message_at','detected_language'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'last_message_at' => 'timestamp',
    ];

    /**
     * Get the channel associated with the conversation.
     *
     * @return BelongsTo
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * Get the contact associated with the conversation.
     *
     * @return BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    /**
     * Dynamically retrieve messages based on platform type.
     *
     * @return ?HasMany
     */
    public function messages(): ?HasMany
    {
        return match ($this->platform) {
            Channel::WHATSAPP_PLATFORM => $this->hasMany(WhatsappMessage::class, 'conversation_id'),

            Channel::LIVECHAT_PLATFORM => $this->hasMany(LiveChatMessage::class, 'conversation_id'),

            Channel::MESSENGER_PLATFORM => $this->hasMany(MessengerMessage::class, 'conversation_id'),

            default => null,
        };
    }


    /**
     * Get the notes associated with the conversation.
     *
     * @return HasMany
     */
    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class, 'conversation_id');
    }

    /**
     * Get the pre-chat form field responses associated with the conversation.
     *
     * @return HasMany
     */
    public function preChatFormFieldResponse(): HasMany
    {
        return $this->hasMany(PreChatFormFieldResponse::class, 'conversation_id');
    }

    /**
     * Get the agents associated with the conversation.
     *
     * @return BelongsToMany
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_agents', 'conversation_id', 'inbox_agent_id')
            ->withPivot('assigned_at', 'removed_at')
            ->withTimestamps()
            ->withTrashed();
    }

    /**
     * Get the current agent assigned to the conversation.
     *
     * @return BelongsToMany|null
     */
    public function currentAgent()
    {
        return $this->agents()
            ->wherePivotNull('removed_at')
            ->latest('pivot_assigned_at')
            ->first();
    }

    public function metaConversationLogs(): HasMany
    {
        return $this->hasMany(MetaConversationLog::class, 'conversation_id');
    }

    public function unreadMessages(): ?HasMany
    {
        return $this->messages()
            ->where('direction', 'RECEIVED')
            ->whereIn('status', ['initiated', 'sent', 'delivered']);
    }

    public function countUnread(): int
    {
        return $this->unreadMessages()?->count() ?? 0;
    }


    public function isInCustomerServiceWindow(): bool
    {
        $windowEndsAt = $this->customerServiceWindowEndsAt();

        if (!$windowEndsAt) {
            return false;
        }

        return time() <= $windowEndsAt;
    }

    public function customerServiceWindowEndsAt(): ?int
    {
        return match ($this->platform) {
            Channel::WHATSAPP_PLATFORM => $this->getWhatsAppWindowEndsAt(),
            Channel::MESSENGER_PLATFORM => $this->getMessengerWindowEndsAt(),
            default => null,
        };
    }

    private function getWhatsAppWindowEndsAt(): ?int
    {
        $messages = $this->whatsappMessages();

        // Get last received message timestamp
        $lastReceivedTimestamp = $messages
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_RECEIVED)
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->value('created_at');

        // Get last sent template message timestamp
        $lastSentTemplateTimestamp = $messages
            ->where('direction', WhatsappMessage::MESSAGE_DIRECTION_SENT)
            ->where('type', WhatsappMessage::MESSAGE_TYPE_TEMPLATE)
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->value('created_at');

        // Take the most recent timestamp that opens a 24-hour window
        $windowOpenerTimestamp = max($lastReceivedTimestamp ?? 0, $lastSentTemplateTimestamp ?? 0);

        if (!$windowOpenerTimestamp) {
            return null;
        }

        return $windowOpenerTimestamp + 86400; // 24 hours window
    }

    private function getMessengerWindowEndsAt(): ?int
    {
        // Get last received message timestamp
        $lastReceivedTimestamp = $this->messengerMessages()
            ->where('direction', MessengerMessage::MESSAGE_DIRECTION_RECEIVED)
            ->whereNotNull('created_at')
            ->orderByDesc('created_at')
            ->value('created_at');

        if (!$lastReceivedTimestamp) {
            return null;
        }

        return $lastReceivedTimestamp + 604800; // 7 days window (7 * 24 * 60 * 60)
    }

    public function isCustomerServiceWindowActive(): bool
    {
        $windowEndsAt = $this->customerServiceWindowEndsAt();

        if (!$windowEndsAt) {
            return false;
        }

        return time() < $windowEndsAt;
    }


    public function shouldChargeForUtilityTemplate(): bool
    {
        // Not applicable if not WhatsApp
        if ($this->platform !== Channel::WHATSAPP_PLATFORM) {
            return false;
        }

        // If no window is open, charge for utility template
        return !$this->isInCustomerServiceWindow();
    }

    public function shouldChargeForTemplate(string $category): bool
    {
        if ($this->platform !== Channel::WHATSAPP_PLATFORM) {
            return false;
        }

        return match ($category) {
            'marketing', 'authentication' => true,
            'utility' => $this->shouldChargeForUtilityTemplate(),
            default => false, // Unknown category
        };
    }

    public function notifyNewMessage(?string $bodyPreview = null): DashboardNotification
    {
        $workspace = $this->channel?->workspaces()->orderBy('workspace_channel.created_at')->first();
        if (!$workspace) {
            throw new \RuntimeException('Workspace not found for conversation');
        }

        $platformLabel = match ($this->platform) {
            \App\Models\Channel::WHATSAPP_PLATFORM => 'WhatsApp',
            \App\Models\Channel::MESSENGER_PLATFORM => 'Messenger',
            \App\Models\Channel::LIVECHAT_PLATFORM => 'Live Chat',
            default => 'Inbox',
        };

        $contactName = trim($this->contact?->display_name ?? '') ?: 'New contact';
        $preview = $bodyPreview ? Str::limit($bodyPreview, 120) : null;

        return $this->dashboardNotifications()->create([
            'title' => "New {$platformLabel} message from {$contactName}",
            'message' => $preview ? "“{$preview}”" : "You’ve got a new message. Jump in to reply.",
            'link' => url("/workspaces/{$workspace->id}/inbox/conversations/{$this->id}"),
            'icon' => $this->platform === \App\Models\Channel::WHATSAPP_PLATFORM ? 'whatsapp' : 'message',
            'category' => 'new-conversation-message',
            'workspace_id' => $workspace->id,
            'organization_id' => $workspace->organization_id,
            'created_at' => now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public function whatsappMessages()
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }
    public function messengerMessages()
    {
        return $this->hasMany(MessengerMessage::class, 'conversation_id');
    }
    public function liveChatMessages()
    {
        return $this->hasMany(LiveChatMessage::class, 'conversation_id');
    }

    public function latestWhatsappMessage()
    {
        return $this->hasOne(WhatsappMessage::class, 'conversation_id')->latestOfMany('created_at');
    }
    public function latestMessengerMessage()
    {
        return $this->hasOne(MessengerMessage::class, 'conversation_id')->latestOfMany('created_at');
    }
    public function latestLiveChatMessage()
    {
        return $this->hasOne(LiveChatMessage::class, 'conversation_id')->latestOfMany('created_at');
    }


    public function getLatestMessageAttribute()
    {
        return $this->latestWhatsappMessage
            ?? $this->latestMessengerMessage
            ?? $this->latestLiveChatMessage;
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    
    public function dashboardNotifications(): HasMany
    {
        return $this->hasMany(DashboardNotification::class, 'conversation_id');
    }

    /**
     * Update the detected language of the conversation.
     *
     * @param string $language
     * @return bool
     */
    public function updateDetectedLanguage(string $language): bool
    {
        if ($this->detected_language !== $language) {
            return $this->update(['detected_language' => $language]);
        }
        return false;
    }

    /**
     * Get the detected language or default.
     *
     * @param string $default
     * @return string
     */
    public function getDetectedLanguageOrDefault(string $default = 'en'): string
    {
        return $this->detected_language ?? $default;
    }

    /**
     * Get the chatbot conversation associated with this conversation.
     */
    public function chatbotConversation(): HasOne
    {
        return $this->hasOne(ChatbotConversation::class);
    }

    /**
     * Check if chatbot is active for this conversation.
     */
    public function isChatbotActive(): bool
    {
        return $this->chatbotConversation?->is_bot_active ?? false;
    }
}
