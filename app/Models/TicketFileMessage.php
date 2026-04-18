<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketFileMessage extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'ticket_file_messages';

    protected $fillable = [
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'caption',
    ];

    /**
     * Get the ticket message that owns this file message.
     */
    public function ticketMessage(): MorphOne
    {
        return $this->morphOne(TicketMessage::class, 'messageable');
    }
    
    // Get URL and other helper methods similar to what you have in TicketAttachment
}