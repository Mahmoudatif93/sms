<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 *
 *
 * @property string $id
 * @property string $workspace_id
 * @property int $created_at
 * @property int $updated_at
 * @property-read Collection<int, Identifier> $identifiers
 * @property-read int|null $identifiers_count
 * @property-read Workspace $workspace
 * @property-read Collection<int, IAMList> $lists
 * @property-read int|null $lists_count
 * @method static Builder|ContactEntity newModelQuery()
 * @method static Builder|ContactEntity newQuery()
 * @method static Builder|ContactEntity query()
 * @method static Builder|ContactEntity whereCreatedAt($value)
 * @method static Builder|ContactEntity whereId($value)
 * @method static Builder|ContactEntity whereUpdatedAt($value)
 * @method static Builder|ContactEntity whereWorkspaceId($value)
 * @property-read Collection<int, ContactAttribute> $attributes
 * @property-read int|null $attributes_count
 * @property-read bool $has_messenger_subscription
 * @property-read string|null $ip_address_from
 * @property-read string|null $name
 * @property-read ContactAttribute|null $hasMessengerSubscriptionAttribute
 * @property-read Collection<int, MessengerConsumer> $messengerConsumers
 * @property-read int|null $messenger_consumers_count
 * @property-read Collection<int, Segment> $segments
 * @property-read int|null $segments_count
 * @method static Builder<static>|ContactEntity withAnyIdentifierKey(array $identifiers)
 * @method static Builder<static>|ContactEntity withAnyIdentifierValue(array $identifiers)
 * @mixin Eloquent
 */
class ContactEntity extends Model
{
    public const IDENTIFIER_TYPE_PHONE = 'phone-number';
    public const IDENTIFIER_TYPE_EMAIL = 'email';
    public const IDENTIFIER_TYPE_IP = 'ip-address'; // UUID as the primary key
    public const ATTRIBUTE_TYPE_DISPALY_NAME = 'display-name';
    public const ATTRIBUTE_TYPE_WHATSAPP_NAME = 'whatsapp-name';
    public const ATTRIBUTE_NAME = 'name';
    public $incrementing = false;
    protected $table = 'contacts';
    protected $keyType = 'string';
    protected $casts = [
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp'
    ];

    protected $fillable = ['id', 'organization_id'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }


    // Relationship with Workspace

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(
            Workspace::class,
            'contact_workspace',
            'contact_id',   // FK on the pivot table pointing to ContactEntity
            'workspace_id'  // FK on the pivot table pointing to Workspace
        )
            ->using(ContactWorkspace::class)
            ->withTimestamps();
    }


    // Relationship with Identifier

    /**
     * Search contacts by any combination of identifiers
     *
     * @param Builder $query
     * @param array $identifiers Array of key-value pairs to search for
     * @return Builder
     */
    public function scopeWithAnyIdentifierKey(Builder $query, array $identifiers): Builder
    {
        return $query->where(function ($query) use ($identifiers) {
            foreach ($identifiers as $key => $value) {
                $query->orWhereHas('identifiers', function ($subQuery) use ($key, $value) {
                    $subQuery->where('key', $key);
                    // ->where('value', $value);
                });
            }
        });
    }

    // Define the relationship between Contact and its Attributes

    public function scopeWithAnyIdentifierValue(Builder $query, array $identifiers): Builder
    {
        return $query->where(function ($query) use ($identifiers) {
            foreach ($identifiers as $key => $value) {
                $query->orWhereHas('identifiers', function ($subQuery) use ($key, $value) {
                    $subQuery->where('value', 'like', '%' . $value . '%');
                    //->where('key', $key);
                    //
                });
            }
        });
    }

    /**
     * Relationship to retrieve lists associated with this contact through a pivot table.
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(IAMList::class, 'contact_list', 'contact_id', 'list_id');
    }

    /**
     * Relationship: A Contact belongs to many Segments.
     */
    public function segments(): BelongsToMany
    {
        return $this->belongsToMany(Segment::class, 'contact_segment', 'contact_id', 'segment_id');
    }

    /**
     * Get the email identifier value for the contact.
     *
     * @return string|null
     */
    public function getEmailIdentifier(): ?string
    {
        return $phoneIdentifier = $this->identifiers()->firstWhere('key', self::IDENTIFIER_TYPE_EMAIL)?->value ?? null;

    }

    public function identifiers(): HasMany
    {
        return $this->hasMany(Identifier::class, 'contact_id');
    }

    public function getPhoneIdentifier(): ?string
    {
        return $phoneIdentifier = $this->identifiers()->firstWhere('key', self::IDENTIFIER_TYPE_PHONE)?->value ?? null;

    }

    /**
     * Get the name identifier value for the contact.
     *
     * @return string|null
     */
    public function getNameIdentifier($platform): ?string
    {
        return match ($platform) {
            Channel::WHATSAPP_PLATFORM => $this->getPhoneNumberIdentifier(),
            Channel::LIVECHAT_PLATFORM => $this->getGeoData(),
            Channel::TICKETING_PLATFORM => $this->getNameAttribute(),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    /**
     * Get the phone-number identifier value for the contact.
     *
     * @return string|null
     */
    public function getPhoneNumberIdentifier(): ?string
    {
        $phoneNumber = $this->identifiers()->firstWhere('key', self::IDENTIFIER_TYPE_PHONE)?->value ?? null;

        return $phoneNumber ? str_replace(' ', '', $phoneNumber) : null;
    }

    public function getWhatsappNameAttribute()
    {
        $whatsappNameAttrDef = AttributeDefinition::where('key', self::ATTRIBUTE_TYPE_WHATSAPP_NAME)->first();
        if (!$whatsappNameAttrDef) {
            return null;
        }
        $whatsappNamettribute = $this->attributes()
            ->where('attribute_definition_id', $whatsappNameAttrDef->id)
            ->first();
        if (!$whatsappNamettribute) {
            return null;
        }
        return $whatsappNamettribute->value;
    }

    protected function getGeoData(): string
    {
        $ipAddress = $this->getIpAddressFromAttribute();
        if (!$ipAddress) {
            return 'Unknown';
        }
        $geoData = getLocationFromIP($ipAddress);
        return ($geoData['city'] ?? 'Unknown') . ', ' . ($geoData['country'] ?? 'Unknown - ') . ' (' . $ipAddress . ')';
    }

    /**
     * Get the IP address for the contact from ContactAttribute.
     *
     * This method searches for an attribute with key 'ip-address' in the contact's attributes.
     * It finds the corresponding AttributeDefinition and returns the value.
     *
     * @return string|null
     */
    public function getIpAddressFromAttribute(): ?string
    {
        // Find attribute definition for IP address
        $ipAttrDef = AttributeDefinition::where('key', self::IDENTIFIER_TYPE_IP)->first();

        if (!$ipAttrDef) {
            return null;
        }

        // Get the attribute value using the attribute definition ID
        $ipAttribute = $this->attributes()
            ->where('attribute_definition_id', $ipAttrDef->id)
            ->first();

        return $ipAttribute?->value ?? null;
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(ContactAttribute::class, 'contact_id');
    }

    public function getNameAttribute(): ?string
    {
        $nameAttrDef = AttributeDefinition::where('key', self::ATTRIBUTE_NAME)->first();

        if (!$nameAttrDef) {
            return null;
        }
        $nameAttr = $this->attributes()
            ->where('attribute_definition_id', $nameAttrDef->id)
            ->first();


        return $nameAttr?->value ?? null;
    }

    public function getDiplayNameAttribute(): ?string
    {
        $nameAttrDef = AttributeDefinition::where('key', self::ATTRIBUTE_TYPE_DISPALY_NAME)->first();

        if (!$nameAttrDef) {
            return null;
        }
        $nameAttr = $this->attributes()
            ->where('attribute_definition_id', $nameAttrDef->id)
            ->first();
        return $nameAttr?->value ?? null;
    }

    public function messengerConsumers(): HasMany
    {
        return $this->hasMany(MessengerConsumer::class, 'contact_id');
    }

    public function hasMessengerSubscriptionAttribute(): HasOne
    {
        return $this->hasOne(ContactAttribute::class, 'contact_id')
            ->whereHas('attributeDefinition', function ($query) {
                $query->where('key', 'subscribed-messenger');
            });
    }

    public function getHasMessengerSubscriptionAttribute(): bool
    {
        return $this->hasMessengerSubscriptionAttribute?->value === '1';
    }


    public function setDisplayName(string $name): void
    {
        $displayNameDefinition = AttributeDefinition::where('key', self::ATTRIBUTE_TYPE_DISPALY_NAME)->first();

        if (!$displayNameDefinition) {
            return;
        }

        $this->attributes()->updateOrCreate(
            ['attribute_definition_id' => $displayNameDefinition->id],
            ['value' => $name]
        );
    }

    public function setWhatsAppName(string $name)
    {
        $whatsappNameDefinition = AttributeDefinition::firstOrCreate(['key' => self::ATTRIBUTE_TYPE_WHATSAPP_NAME, 'display_name' => 'Whatsapp Name']);
        $this->attributes()->updateOrCreate(
            ['attribute_definition_id' => $whatsappNameDefinition->id],
            ['value' => $name]
        );
    }

    public function setPhoneNumberIdentifier(string $phone): void
    {
        $this->identifiers()->updateOrCreate(
            ['key' => self::IDENTIFIER_TYPE_PHONE],
            ['value' => $phone]
        );
    }

    public function markAsWhatsappSubscribed(): void
    {
        $whatsappAttrDef = AttributeDefinition::where('key', 'subscribed-whatsapp')->first();

        if (!$whatsappAttrDef) {
            return;
        }

        $this->attributes()->updateOrCreate(
            ['attribute_definition_id' => $whatsappAttrDef->id],
            ['value' => true]
        );
    }


    public function campaignMessageLogs(): ContactEntity|Builder|HasMany
    {
        return $this->hasMany(CampaignMessageLog::class, 'contact_id');
    }


    public function getWhatsappName()
    {

    }



}
