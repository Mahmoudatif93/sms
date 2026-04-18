<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class WebhookService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Get the webhooks for this service.
     */
    public function webhooks()
    {
        return $this->hasMany(Webhook::class, 'service_id');
    }

    /**
     * Get the events for this service.
     */
    public function events()
    {
        return $this->hasMany(WebhookEvent::class, 'webhook_service_id');
    }

    /**
     * Scope a query to only include active services.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}