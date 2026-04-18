<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsPlanTransaction extends Model
{
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id',
        'points_allocated',
        'price_per_point',
        'currency',
        'organization_id',
        'payment_id'  // Note: singular form, not payments_id
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string)\Str::uuid(); // Generate a UUID
        });
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
