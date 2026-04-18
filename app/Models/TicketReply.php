<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketReply extends Model
{
    use HasFactory;
    protected $table = 'ticket_reply'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'content',
        'date',
        'supervisor_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }



}
