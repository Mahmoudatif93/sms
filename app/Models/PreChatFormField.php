<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PreChatFormField extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pre_chat_form_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pre_chat_form_id',
        'type',
        'name',
        'label',
        'placeholder',
        'required',
        'enabled',
        'options',
        'validation',
        'order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'required' => 'boolean',
        'enabled' => 'boolean',
        'options' => 'json',
        'validation' => 'json',
        'order' => 'integer',
    ];

    /**
     * Get the pre-chat form that owns the field.
     */
    public function preChatForm(): BelongsTo
    {
        return $this->belongsTo(PreChatForm::class);
    }

    /**
     * Get the responses for this field.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(PreChatFormFieldResponse::class, 'field_id');
    }
}