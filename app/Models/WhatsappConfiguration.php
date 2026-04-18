<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property string $connector_id
 * @property int $business_manager_account_id
 * @property int $whatsapp_business_account_id
 * @property int $primary_whatsapp_phone_number_id
 * @property string $status
 * @property int $is_sandbox
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read Connector $connector
 * @property-read WhatsappBusinessAccount $whatsappBusinessAccount
 * @method static Builder|WhatsappConfiguration newModelQuery()
 * @method static Builder|WhatsappConfiguration newQuery()
 * @method static Builder|WhatsappConfiguration query()
 * @method static Builder|WhatsappConfiguration whereBusinessManagerAccountId($value)
 * @method static Builder|WhatsappConfiguration whereConnectorId($value)
 * @method static Builder|WhatsappConfiguration whereCreatedAt($value)
 * @method static Builder|WhatsappConfiguration whereId($value)
 * @method static Builder|WhatsappConfiguration whereIsSandbox($value)
 * @method static Builder|WhatsappConfiguration whereStatus($value)
 * @method static Builder|WhatsappConfiguration whereUpdatedAt($value)
 * @method static Builder|WhatsappConfiguration whereWhatsappBusinessAccountId($value)
 * @property-read WhatsappPhoneNumber|null $whatsappPhoneNumber
 * @method static Builder<static>|WhatsappConfiguration wherePrimaryWhatsappPhoneNumberId($value)
 * @mixin Eloquent
 */
class WhatsappConfiguration extends Model
{

    use HasUuids;

    protected $table = 'whatsapp_configurations';

    protected $fillable = [
        'id',
        'connector_id',
        'business_manager_account_id',
        'whatsapp_business_account_id',
        'primary_whatsapp_phone_number_id',
        'status',
        'is_sandbox'
    ];

    protected $casts = ['created_at' => 'timestamp', 'updated_at' => 'timestamp'];


    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    /**
     * Relationship to the WhatsappBusinessAccount.
     */
    public function whatsappBusinessAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsappBusinessAccount::class, 'whatsapp_business_account_id');
    }

    public function whatsappPhoneNumber(): BelongsTo
    {
        return $this->belongsTo(WhatsappPhoneNumber::class, 'primary_whatsapp_phone_number_id');
    }

}
