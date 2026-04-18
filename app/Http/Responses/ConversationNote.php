<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

class ConversationNote extends DataInterface
{
   public string $id;
   public string $conversation_id;
   public mixed $user=null;
    public string $content;
    public int $created_at;
    public int $updated_at;


        

    public function __construct(\App\Models\ConversationNote $note)
    {
        $this->id = $note->id;
        $this->conversation_id = $note->conversation_id;
        $this->user = $note->user;
        $this->content = $note->content;
        $this->created_at = $note->created_at;
        $this->updated_at = $note->updated_at;
    }
}