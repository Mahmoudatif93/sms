<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\OrganizationWhatsappRateLine
 *
 * Represents an overridden WhatsApp rate line for a specific organization.
 *
 * @property int $id
 * @property string $organization_id
 * @property int $whatsapp_rate_line_id
 * @property float|null $custom_price
 * @property string|null $currency
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Organization $organization
 * @property-read WhatsappRateLine $whatsappRateLine
 *
 * @method static Builder|OrganizationWhatsappRateLine newModelQuery()
 * @method static Builder|OrganizationWhatsappRateLine newQuery()
 * @method static Builder|OrganizationWhatsappRateLine query()
 * @method static Builder|OrganizationWhatsappRateLine whereOrganizationId($value)
 * @method static Builder|OrganizationWhatsappRateLine whereWhatsappRateLineId($value)
 *
 * @mixin Eloquent
 */
class OrganizationWhatsappRateLine extends Model
{
    protected $fillable = [
        'organization_id',
        'whatsapp_rate_line_id',
        'custom_price',
        'currency'
    ];

    /**
     * Get the organization that owns this custom rate line.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the base WhatsApp rate line this custom rate refers to.
     */
    public function whatsappRateLine(): BelongsTo
    {
        return $this->belongsTo(WhatsappRateLine::class);
    }
}
