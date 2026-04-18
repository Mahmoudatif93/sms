<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Class ConversationNote
 *
 * Represents a private note added by an agent to a conversation.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $conversation_id Foreign Key - The conversation this note belongs to
 * @property string $user_id Foreign Key - The user (agent) who created the note
 * @property string $content The content of the note
 * @property int|null $created_at Timestamp when the note was created
 * @property int|null $updated_at Timestamp when the note was last updated
 * @property int|null $deleted_at Timestamp when the note was soft-deleted (null if not deleted)
 *
 * @property-read Conversation $conversation The conversation this note belongs to
 * @property-read User $user The user who created the note
 */
class ConversationNote extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'conversation_notes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['conversation_id', 'user_id', 'content'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    /**
     * Get the conversation this note belongs to.
     *
     * @return BelongsTo
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    /**
     * Get the user (agent) who created this note.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}