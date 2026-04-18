<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PostChatFormField extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_chat_form_fields';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'post_chat_form_id',
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
     * Get the post-chat form that owns the field.
     */
    public function postChatForm(): BelongsTo
    {
        return $this->belongsTo(PostChatForm::class);
    }

    /**
     * Get the responses for this field.
     */
    // public function responses(): HasMany
    // {
    //     return $this->hasMany(PostChatFormResponse::class, 'field_id');
    // }
}
