<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WhatsappConversationBilling extends Model
{

    protected $table = 'whatsapp_conversation_billings'; // Replace with your actual table name

    protected $fillable = [
        'conversation_id',
        'type',
        'cost',
        'original_cost',
        'currency',
        'billable',
        'walletable_id',
        'walletable_type'
    ];

    /**
     * Define a one-to-one relationship with WhatsappConversation.
     */
    public function whatsappConversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id', 'id');
    }

    // Polymorphic relationship for the Wallet
    public function walletable(): MorphTo
    {
        return $this->morphTo();
    }
}
