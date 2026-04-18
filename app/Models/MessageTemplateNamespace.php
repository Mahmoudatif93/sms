<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageTemplateNamespace extends Model
{
    use HasFactory;

    protected $fillable = [
        'whatsapp_business_account_id', 'namespace'
    ];

    public function whatsappBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class);
    }
}
