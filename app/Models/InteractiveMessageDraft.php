<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Interactive Message Draft Model
 *
 * Stores reusable interactive message drafts (button/list).
 *
 * @property int $id
 * @property string $workspace_id
 * @property string $name
 * @property string|null $description
 * @property string $interactive_type
 * @property array|null $header
 * @property string $body
 * @property string|null $footer
 * @property array|null $buttons
 * @property string|null $list_button_text
 * @property array|null $sections
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class InteractiveMessageDraft extends Model
{
    use SoftDeletes;

    // Interactive types
    public const TYPE_BUTTON = 'button';
    public const TYPE_LIST = 'list';

    protected $table = 'interactive_message_drafts';

    protected $fillable = [
        'workspace_id',
        'name',
        'description',
        'interactive_type',
        'header',
        'body',
        'footer',
        'buttons',
        'list_button_text',
        'sections',
        'is_active',
    ];

    protected $casts = [
        'header' => 'array',
        'buttons' => 'array',
        'sections' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get the workspace that owns this draft.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Scope to get active drafts.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('interactive_type', $type);
    }

    /**
     * Check if this is a button type.
     */
    public function isButtonType(): bool
    {
        return $this->interactive_type === self::TYPE_BUTTON;
    }

    /**
     * Check if this is a list type.
     */
    public function isListType(): bool
    {
        return $this->interactive_type === self::TYPE_LIST;
    }

    /**
     * Build the WhatsApp API interactive payload.
     */
    public function buildPayload(): array
    {
        $payload = [
            'type' => $this->interactive_type,
            'body' => ['text' => $this->body],
        ];

        if ($this->header) {
            $payload['header'] = $this->header;
        }

        if ($this->footer) {
            $payload['footer'] = ['text' => $this->footer];
        }

        if ($this->isButtonType()) {
            $payload['action'] = [
                'buttons' => collect($this->buttons)->map(fn($btn) => [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $btn['id'],
                        'title' => $btn['title'],
                    ],
                ])->toArray(),
            ];
        } elseif ($this->isListType()) {
            $payload['action'] = [
                'button' => $this->list_button_text ?? 'View Options',
                'sections' => collect($this->sections)->map(fn($section) => [
                    'title' => $section['title'],
                    'rows' => collect($section['rows'])->map(fn($row) => array_filter([
                        'id' => $row['id'],
                        'title' => $row['title'],
                        'description' => $row['description'] ?? null,
                    ]))->toArray(),
                ])->toArray(),
            ];
        }

        return $payload;
    }

    /**
     * Get available interactive types.
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_BUTTON => 'Button (Quick Reply)',
            self::TYPE_LIST => 'List (Menu)',
        ];
    }
}

