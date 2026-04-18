<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreChatForm extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pre_chat_forms';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'channel_id',
        'widget_id',
        'enabled',
        'title',
        'description',
        'submit_button_text',
        'require_fields',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'require_fields' => 'boolean',
    ];

    /**
     * Get the channel that owns the pre-chat form.
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Get the fields for the pre-chat form.
     */
    public function fields(): HasMany
    {
        return $this->hasMany(PreChatFormField::class)->orderBy('order');
    }

    /**
     * Check if the form has any required fields.
     */
    public function hasRequiredFields(): bool
    {
        return $this->fields()->where('required', true)->exists();
    }

    /**
     * Get the widget associated with this pre-chat form through the channel's connector.
     */
    public function widget()
    {
        return $this->channel->connector->liveChatConfiguration->widget ?? null;
    }
}
