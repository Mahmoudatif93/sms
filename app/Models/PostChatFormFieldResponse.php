<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PostChatFormFieldResponse extends Model
{
    use HasFactory, SoftDeletes, HasUuids;
    protected $table = 'post_chat_form_field_responses';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'field_id',
        'conversation_id',
        'contact_id',
        'value',
    ];

     /**
     * Get the field that this response is for.
     */
    public function field(): BelongsTo
    {
        return $this->belongsTo(PostChatFormField::class, 'field_id');
    }

     /**
     * Get the conversation that this response belongs to.
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }


    /**
     * Get the visitor that submitted this response.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    /**
     * Get the message that references this response.
     */
    public function message(): MorphOne
    {
        return $this->morphOne(LiveChatMessage::class, 'messageable');
    }
    
    /**
     * Get all responses for a specific conversation.
     */
    public static function getConversationResponses($conversationID)
    {
        return self::where('conversation_id', $conversationID)
                  ->with('field')
                  ->get()
                  ->map(function($response) {
                      return [
                          'field_name' => $response->field->name,
                          'field_label' => $response->field->label,
                          'value' => $response->value
                      ];
                  })
                  ->keyBy('field_name')
                  ->toArray();
    }
    
    /**
     * Create responses from form data.
     */
    public static function createFromFormData($conversationId, $contactID, $formData, $preChatForm)
    {
        $responses = [];
        foreach ($formData as $fieldName => $value) {
            $field = $preChatForm->fields()->where('name', $fieldName)->first();
            
            if ($field) {
                $response = self::create([
                    'field_id' => $field->id,
                    'conversation_id' => $conversationId,
                    'contact_id' => $contactID,
                    'value' => $value,
                ]);
                
                $responses[] = $response;
            }
        }
        
        return $responses;
    }
}
