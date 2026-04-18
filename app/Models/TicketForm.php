<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TicketForm extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'iframe_token',
        'description',
        'theme_color',
        'success_message',
        'submit_button_text',
        'license_id',
        'license_expires_at',
        'is_active',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'license_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($form) {
            if (empty($form->slug)) {
                $form->slug = Str::slug($form->name . '-' . Str::random(6));
            }
        });
    }

    /**
     * Get the organization that owns the contact form.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the configurations for the contact form.
     */
    public function ticketConfigurations()
    {
        return $this->belongsTo(TicketConfiguration::class,'id','ticket_form_id');
    }
    
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the license associated with the contact form.
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(TicketFormLicense::class, 'license_id');
    }

    /**
     * Get the fields for the contact form.
     */
    public function ticketFormFields(): HasMany
    {
        return $this->hasMany(TicketFormField::class)->orderBy('order');
    }

    /**
     * Get the submissions for the contact form.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(TicketFormSubmission::class);
    }

    /**
     * Check if the form is available for use.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        // Check if the form is active
        if (!$this->is_active) {
            return false;
        }

        // Check if the license has expired
        if ($this->license_expires_at && $this->license_expires_at->isPast()) {
            return false;
        }

        // If no license is set, or if license checks pass, the form is available
        return true;
    }

    /**
     * Get the embed code for this form.
     *
     * @return string
     */
    public function getEmbedCode(): ?string
    {
        if(!empty($this->iframe_token)){
            return '<iframe src="' . route('ticket.iframe.form', ['token' => $this->iframe_token]) . '" width="100%" height="600px" frameborder="0"></iframe>';
        }
        return null;
    }

    /**
     * Get the JavaScript embed code for this form.
     *
     * @return string
     */
    public function getJsEmbedCode(): ?string
    {
        if(!empty($this->iframe_token)){
            return '<script src="' . route('ticket.iframe.script', ['token' => $this->iframe_token]) . '"></script><div id="ticket-form-container"></div>';
        }
        return null;
        
    }
}