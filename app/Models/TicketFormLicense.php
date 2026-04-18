<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TicketFormLicense extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workspace_id',
        'license_key',
        'valid_from',
        'expires_at',
        'max_forms',
        'max_submissions_per_month',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'valid_from' => 'datetime',
        'expires_at' => 'datetime',
        'max_forms' => 'integer',
        'max_submissions_per_month' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($license) {
            if (empty($license->license_key)) {
                $license->license_key = self::generateLicenseKey();
            }
        });
    }

    /**
     * Generate a unique license key.
     *
     * @return string
     */
    public static function generateLicenseKey(): string
    {
        $key = strtoupper(Str::random(5) . '-' . Str::random(5) . '-' . Str::random(5) . '-' . Str::random(5));
        
        // Ensure the key is unique
        while (self::where('license_key', $key)->exists()) {
            $key = strtoupper(Str::random(5) . '-' . Str::random(5) . '-' . Str::random(5) . '-' . Str::random(5));
        }
        
        return $key;
    }

    /**
     * Get the workspace that owns the license.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the contact forms associated with this license.
     */
    public function ticketForms(): HasMany
    {
        return $this->hasMany(TicketForm::class, 'license_id');
    }

    /**
     * Check if the license is valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->is_active && 
               $this->valid_from->isPast() && 
               $this->expires_at->isFuture();
    }

    /**
     * Check if the license has reached its form limit.
     *
     * @return bool
     */
    public function hasReachedFormLimit(): bool
    {
        if (is_null($this->max_forms)) {
            return false; // No limit
        }
        
        return $this->ticketForms()->count() >= $this->max_forms;
    }

    /**
     * Get the number of submissions in the current month.
     *
     * @return int
     */
    public function getCurrentMonthSubmissions(): int
    {
        $formIds = $this->ticketForms()->pluck('id')->toArray();
        if (empty($formIds)) {
            return 0;
        }
        
        return TicketFormSubmission::whereIn('contact_form_id', $formIds)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();
    }

    /**
     * Check if the license has reached its monthly submission limit.
     *
     * @return bool
     */
    public function hasReachedSubmissionLimit(): bool
    {
        if (is_null($this->max_submissions_per_month)) {
            return false; // No limit
        }
        
        return $this->getCurrentMonthSubmissions() >= $this->max_submissions_per_month;
    }

    /**
     * Calculate the number of days until expiration.
     * 
     * @return int|null
     */
    public function daysUntilExpiration(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        
        return max(0, now()->diffInDays($this->expires_at, false));
    }
}