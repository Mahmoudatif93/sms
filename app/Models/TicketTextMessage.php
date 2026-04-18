<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketTextMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'ticket_text_messages';

    protected $fillable = [
        'content',
    ];

    /**
     * Get the ticket message that owns this text message.
     */
    public function ticketMessage(): MorphOne
    {
        return $this->morphOne(TicketMessage::class, 'messageable');
    }
}