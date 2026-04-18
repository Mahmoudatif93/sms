<?php

namespace App\Models;

use App\Enums\Workflow\TriggerType;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id The Whatsapp Message Template ID.
 * @property int $whatsapp_business_account_id Foreign key to WhatsApp business accounts
 * @property string $name The name of the template, with a maximum of 512 characters.
 * @property string $category The category of the template. Possible values: AUTHENTICATION, MARKETING, UTILITY.
 * @property string $status The status of the template. Possible values: PENDING, APPROVED, REJECTED.
 * @property string $language Template language Code (e.g., en_US)
 * @property bool $allow_category_change Allow category change. If set to true, the system may automatically assign a category.
 * @property string|null $library_template_name The optional utility template name, if available.
 * @property Carbon|null $created_at Creation timestamp
 * @property Carbon|null $updated_at Last update timestamp
 * @property-read WhatsappBusinessAccount|null $businessAccount
 * @property-read Collection<int, WhatsappTemplateHeaderComponent> $headerComponents
 * @property-read int|null $header_components_count
 * @method static Builder|WhatsappMessageTemplate newModelQuery()
 * @method static Builder|WhatsappMessageTemplate newQuery()
 * @method static Builder|WhatsappMessageTemplate query()
 * @method static Builder|WhatsappMessageTemplate whereAllowCategoryChange($value)
 * @method static Builder|WhatsappMessageTemplate whereCategory($value)
 * @method static Builder|WhatsappMessageTemplate whereCreatedAt($value)
 * @method static Builder|WhatsappMessageTemplate whereId($value)
 * @method static Builder|WhatsappMessageTemplate whereLanguage($value)
 * @method static Builder|WhatsappMessageTemplate whereLibraryTemplateName($value)
 * @method static Builder|WhatsappMessageTemplate whereName($value)
 * @method static Builder|WhatsappMessageTemplate whereStatus($value)
 * @method static Builder|WhatsappMessageTemplate whereUpdatedAt($value)
 * @method static Builder|WhatsappMessageTemplate whereWhatsappBusinessAccountId($value)
 * @mixin Eloquent
 */
class WhatsappMessageTemplate extends Model
{

    public static function boot(): void
    {
        parent::boot();

        static::deleting(function ($template) {
            // Delete related components
            $template->headerComponent()->delete();
            $template->bodyComponent()->delete();
        });
    }
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_message_templates';
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    /**
     * The type of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'unsignedBigInteger';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'whatsapp_business_account_id',
        'name',
        'category',
        'status',
        'language',
        'allow_category_change',
        'library_template_name',
        'created_at',
        'updated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'allow_category_change' => 'boolean',
    ];

    /**
     * Get the business account that owns the template.
     */
    public function businessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }

    /**
     * Get the header component associated with the template.
     */
    public function headerComponent(): HasOne
    {
        return $this->hasOne(WhatsappTemplateHeaderComponent::class, 'template_id');
    }

    /**
     * Get the body component associated with the template.
     */
    public function bodyComponent(): HasOne
    {
        return $this->hasOne(WhatsappTemplateBodyComponent::class, 'template_id');
    }

    /**
     * Get the footer component associated with the template.
     */
    public function footerComponent(): HasOne
    {
        return $this->hasOne(WhatsappTemplateFooterComponent::class, 'template_id');
    }

    /**
     * Fetch the template with all components (header, body, examples) eagerly loaded.
     *
     * @return self
     */
    public function loadAllComponents(): WhatsappMessageTemplate
    {
        return $this->load([
            'headerComponent.textComponent.textExamples',
            'bodyComponent.textExamples',
        ]);
    }

    public function authenticationButtonComponents(): HasMany
    {
        return $this->hasMany(WhatsappAuthTemplateButtonComponent::class, 'template_id');
    }

    /**
     * Get the workflows associated with this template.
     * Note: Uses JSON query on trigger_config->template_id
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(WhatsappWorkflow::class, 'trigger_config->template_id', 'id')
            ->where('trigger_type', TriggerType::TEMPLATE_STATUS->value);
    }

    /**
     * Get active workflows for this template.
     */
    public function activeWorkflows(): HasMany
    {
        return $this->workflows()->where('is_active', true);
    }

    /**
     * Check if this template has any active workflows.
     */
    public function hasActiveWorkflows(): bool
    {
        return WhatsappWorkflow::query()
            ->where('trigger_type', TriggerType::TEMPLATE_STATUS->value)
            ->whereJsonContains('trigger_config->template_id', $this->id)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get workflows for a specific trigger status.
     */
    public function getWorkflowsForTrigger(string $triggerStatus)
    {
        return $this->activeWorkflows()
            ->where('trigger_status', $triggerStatus)
            ->orderBy('priority', 'desc')
            ->with('activeActions')
            ->get();
    }
}
