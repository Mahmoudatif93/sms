<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\OrganizationWhatsappSetting
 *
 * Stores WhatsApp settings specific to an organization, including billing configuration and rate usage.
 *
 * @property int $id
 * @property string $organization_id
 * @property bool $use_custom_rates
 * @property string $who_pays_meta         // 'client' or 'provider'
 * @property string $wallet_charge_mode    // 'none', 'markup_only', 'meta_only', or 'full'
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Organization $organization
 *
 * @method static Builder|OrganizationWhatsappSetting newModelQuery()
 * @method static Builder|OrganizationWhatsappSetting newQuery()
 * @method static Builder|OrganizationWhatsappSetting query()
 * @method static Builder|OrganizationWhatsappSetting whereId($value)
 * @method static Builder|OrganizationWhatsappSetting whereOrganizationId($value)
 * @method static Builder|OrganizationWhatsappSetting whereUseCustomRates($value)
 * @method static Builder|OrganizationWhatsappSetting whereWhoPaysMeta($value)
 * @method static Builder|OrganizationWhatsappSetting whereWalletChargeMode($value)
 * @method static Builder|OrganizationWhatsappSetting whereCreatedAt($value)
 * @method static Builder|OrganizationWhatsappSetting whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class OrganizationWhatsappSetting extends Model
{
    protected $fillable = [
        'organization_id',
        'use_custom_rates',
        'who_pays_meta',
        'wallet_charge_mode',
        'markup_percentage'
    ];

    protected $casts = [
        'use_custom_rates' => 'boolean',
        'who_pays_meta' => 'string',
        'wallet_charge_mode' => 'string',
        'markup_percentage' => 'float'
    ];

    /**
     * Get the owning organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
