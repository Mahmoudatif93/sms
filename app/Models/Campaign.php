<?php

namespace App\Models;

use App\Models\ContactEntity;
use App\Traits\WhatsappMediaManager;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Http\Responses\Contact;

/**
 *
 *
 * @property string $id
 * @property string $name
 * @property string $type
 * @property string $send_time_method
 * @property Carbon|null $send_time
 * @property string|null $time_zone
 * @property string $status
 * @property int $whatsapp_message_template_id
 * @property string $workspace_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CampaignList> $campaignLists
 * @property-read int|null $campaign_lists_count
 * @property-read Collection<int, IAMList> $lists
 * @property-read int|null $lists_count
 * @property-read Collection<int, WhatsappMessage> $messages
 * @property-read int|null $messages_count
 * @property-read WhatsappMessageTemplate $whatsappMessageTemplate
 * @method static Builder|Campaign newModelQuery()
 * @method static Builder|Campaign newQuery()
 * @method static Builder|Campaign query()
 * @method static Builder|Campaign whereCreatedAt($value)
 * @method static Builder|Campaign whereId($value)
 * @method static Builder|Campaign whereName($value)
 * @method static Builder|Campaign whereSendTime($value)
 * @method static Builder|Campaign whereSendTimeMethod($value)
 * @method static Builder|Campaign whereStatus($value)
 * @method static Builder|Campaign whereTimeZone($value)
 * @method static Builder|Campaign whereType($value)
 * @method static Builder|Campaign whereUpdatedAt($value)
 * @method static Builder|Campaign whereWhatsappMessageTemplateId($value)
 * @method static Builder|Campaign whereWorkspaceId($value)
 * @mixin Eloquent
 */
class Campaign extends Model
{

    use WhatsappMediaManager;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $casts = [
        'send_time' => 'datetime:Y-m-d\TH:i:s.u\Z',
        'header_variables' => 'array',   // Cast for header variables
        'body_variables' => 'array',     // Cast for body variables
    ];

    const STATUS_DRAFT = 'draft';
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_INPROGRESS = 'in_progress';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid();
            $model->status = self::STATUS_DRAFT; // Set default status
        });
    }

    protected $fillable = [
        'name',
        'type',
        'send_time_method',
        'whatsapp_message_template_id',
        'workspace_id',
        'send_time',
        'time_zone',
        'status',
        'whatsapp_phone_number_id',
        'header_variables',
        'body_variables',
        'channel_id'
    ];

    /**
     * Get the campaign lists associated with the campaign.
     */
    public function campaignLists(): HasMany
    {
        return $this->hasMany(CampaignList::class, 'campaign_id');
    }

    /**
     * Get the WhatsApp message template associated with the campaign.
     */
    public function whatsappMessageTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappMessageTemplate::class, 'whatsapp_message_template_id');
    }

    /**
     * Set template variables for both header and body
     *
     * @param array $headerVars
     * @param array $bodyVars
     * @return void
     */
    public function setTemplateVariables(array $headerVars = [], array $bodyVars = []): void
    {
        $this->header_variables = $headerVars;
        $this->body_variables = $bodyVars;
        $this->save();
    }

    /**
     * Get all template variables
     *
     * @return array
     */
    public function getTemplateVariables(): array
    {
        return [
            [
                'type' => 'header',
                'parameters' => $this->header_variables ?? []
            ],
            [
                'type' => 'body',
                'parameters' => $this->body_variables ?? []
            ]
        ];
    }

    /**
     * Validate if all required template variables are set
     *
     * @return bool
     */
    public function hasAllRequiredVariables(): bool
    {
        $template = $this->whatsappMessageTemplate;
        if (!$template) {
            return false;
        }

        $requiredHeaderVars = $template->header_variables ?? [];
        $requiredBodyVars = $template->body_variables ?? [];

        $headerComplete = empty($requiredHeaderVars) ||
            (!empty($this->header_variables) &&
                count(array_intersect_key(array_flip($requiredHeaderVars), $this->header_variables)) === count($requiredHeaderVars));

        $bodyComplete = empty($requiredBodyVars) ||
            (!empty($this->body_variables) &&
                count(array_intersect_key(array_flip($requiredBodyVars), $this->body_variables)) === count($requiredBodyVars));

        return $headerComplete && $bodyComplete;
    }

    /**
     * Replace template variables with actual values
     *
     * @param ContactEntity $contactEntity
     * @return array
     */
    public function compileTemplate(ContactEntity $contactEntity): array
    {
        // Map contact attributes by attribute definition ID
        $contactAttributes = $contactEntity->attributes()
            ->with('attributeDefinition')
            ->get()
            ->mapWithKeys(function ($attribute) {
                return [$attribute->attributeDefinition->id => $attribute->value];
            })
            ->toArray();

        $compiledTemplate = [];

        if (!empty($this->header_variables)) {
            $headerParameters = array_map(function ($value) use ($contactAttributes) {
                // Handle text type with dynamic key
                if (isset($value['type']) && $value['type'] === 'text') {
                    $text = $value['text'] ?? null;

                    // If it's dynamic, replace with contact attribute
                    if (isset($value['key']) && isset($contactAttributes[$value['key']])) {
                        $text = $contactAttributes[$value['key']];
                    }

                    return [
                        'type' => 'text',
                        'text' => $text
                    ];
                }

                // Handle image type
                if (isset($value['type']) && $value['type'] === 'image' && isset($value['image']['link'])) {
                    return [
                        'type' => 'image',
                        'image' => [
                            'link' => $this->regenerateSignedPreviewUrlFromLink($value['image']['link'])
                        ]
                    ];
                }
                if (isset($value['type']) && $value['type'] === 'video' && isset($value['video']['link'])) {
                    return [
                        'type' => 'video',
                        'video' => [
                            'link' => $this->regenerateSignedPreviewUrlFromLink($value['video']['link'])
                        ]
                    ];
                }

                // Handle image type
                if (isset($value['type']) && $value['type'] === 'document' && isset($value['document']['link'])) {
                    return [
                        'type' => 'document',
                        'document' => [
                            'link' => $this->regenerateSignedPreviewUrlFromLink($value['document']['link'])
                        ]
                    ];
                }

                return null; // skip unknown or malformed entries
            }, $this->header_variables);

            // Filter out null/empty results
            $headerParameters = array_filter($headerParameters, fn($param) => !empty($param));

            if (!empty($headerParameters)) {
                $compiledTemplate[] = [
                    'type' => 'header',
                    'parameters' => array_values($headerParameters)
                ];
            }
        }
        // Process body variables - include only "type" and "text", removing "key"
        if (!empty($this->body_variables)) {
            $bodyParameters = array_map(function ($value) use ($contactAttributes) {
                if (is_array($value) && isset($value['key']) && isset($contactAttributes[$value['key']])) {
                    return [
                        'type' => 'text',
                        'text' => $contactAttributes[$value['key']]
                    ]; // Only include "type" and "text"
                }

                return [
                    'type' => 'text',
                    'text' => $value['text']
                ];
            }, $this->body_variables);


            $bodyParameters = array_filter($bodyParameters, fn($value) => !empty($value));

            if (!empty($bodyParameters)) {
                $compiledTemplate[] = [
                    'type' => 'body',
                    'parameters' => array_values($bodyParameters)
                ];
            }
        }

        return $compiledTemplate;
    }




    /**
     * Get all contacts related to this campaign through associated lists.
     */
    public function getContacts(): Collection
    {
        return ContactEntity::whereHas('lists', function ($query) {
            $query->whereIn('lists.id', $this->lists()->pluck('lists.id')->toArray());
        })->with('identifiers')->get();
    }

    /**
     * Get the list IDs associated with the campaign.
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(IAMList::class, 'campaign_list', 'campaign_id', 'list_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'campaign_id');
    }

    public function whatsappPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'whatsapp_phone_number_id');
    }


    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function messageLogs(): Campaign|Builder|HasMany
    {
        return $this->hasMany(CampaignMessageLog::class, 'campaign_id');
    }

    public function contactsQuery(): \App\Models\ContactEntity|Builder|\LaravelIdea\Helper\App\Models\_IH_ContactEntity_QB
    {
        return ContactEntity::whereHas('lists', function ($q) {
            $q->whereIn(
                'lists.id',
                $this->lists()->pluck('lists.id')->toArray()
            );
        })->with('identifiers');
    }

    public function contactsCount(): int
    {
        return $this->contactsQuery()->count();
    }


}
