<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookEvent extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'webhook_service_id',
        'description',
        'payload_schema',
        'is_active'
    ];

    protected $casts = [
        'payload_schema' => 'array',
        'is_active' => 'boolean'
    ];

    /**
     * Get the webhooks associated with this event.
     */
    public function webhooks()
    {
        return $this->hasMany(Webhook::class, 'event', 'name');
    }

    /**
     * Get the webhook service that owns the event.
     */
    public function webhookService()
    {
        return $this->belongsTo(WebhookService::class, 'webhook_service_id');
    }

    /**
     * Scope a query to only include active events.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get events by service.
     */
    public function scopeByService($query, $service)
    {
        return $query->where('service', $service);
    }
}