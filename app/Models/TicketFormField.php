<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFormField extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ticket_form_id',
        'label',
        'type',
        'placeholder',
        'options',
        'is_required',
        'order',
        'validation_rules',
        'help_text',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
    ];

    /**
     * Get the contact form that owns the field.
     */
    public function ticketForm(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class);
    }

    /**
     * Get the validation rules for this field.
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        $rules = [];

        // Base rules based on field type
        switch ($this->type) {
            case 'email':
                $rules[] = 'email';
                break;
            case 'tel':
                $rules[] = 'regex:/^[0-9\+\-\(\) ]+$/';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'date':
                $rules[] = 'date';
                break;
            case 'url':
                $rules[] = 'url';
                break;
            default:
                $rules[] = 'string';
                break;
        }

        // Add required rule if applicable
        if ($this->is_required) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Add any custom validation rules
        if (!empty($this->validation_rules)) {
            $rules = array_merge($rules, $this->validation_rules);
        }

        return $rules;
    }

    /**
     * Get the HTML input type for this field.
     *
     * @return string
     */
    public function getHtmlType(): string
    {
        switch ($this->type) {
            case 'textarea':
                return 'textarea';
            case 'select':
                return 'select';
            case 'checkbox':
                return 'checkbox';
            case 'radio':
                return 'radio';
            case 'date':
                return 'date';
            case 'time':
                return 'time';
            case 'file':
                return 'file';
            default:
                return $this->type; // text, email, tel, number, url, etc.
        }
    }
}