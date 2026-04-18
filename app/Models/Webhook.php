<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Webhook extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    protected $table = 'webhooks';
    protected $fillable = [
        "organization_id",
        "signing_key",        
        "url",
        "service_id",
        "event_id",
        "channel_id",
        "is_active",
    ];
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string)\Str::uuid(); // Generate a UUID
        });
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function service()
    {
        return $this->belongsTo(WebhookService::class,"service_id");
    }

    public function event()
    {
        return $this->belongsTo(WebhookEvent::class,"event_id");
    }
}
