<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id The Whatsapp business account ID.
 * @property int $business_manager_account_id The business manager account ID connected to it.
 * @property string|null $name The name of the Whatsapp business account.
 * @property int $is_using_public_test_number Is using public test number.
 * @property string|null $currency The currency of the Whatsapp business account.
 * @property string|null $message_template_namespace The message_template_namespace of the Whatsapp business account.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BusinessManagerAccount $businessManagerAccount
 * @method static Builder|WhatsappBusinessAccount newModelQuery()
 * @method static Builder|WhatsappBusinessAccount newQuery()
 * @method static Builder|WhatsappBusinessAccount query()
 * @method static Builder|WhatsappBusinessAccount whereBusinessManagerAccountId($value)
 * @method static Builder|WhatsappBusinessAccount whereCreatedAt($value)
 * @method static Builder|WhatsappBusinessAccount whereCurrency($value)
 * @method static Builder|WhatsappBusinessAccount whereId($value)
 * @method static Builder|WhatsappBusinessAccount whereIsUsingPublicTestNumber($value)
 * @method static Builder|WhatsappBusinessAccount whereMessageTemplateNamespace($value)
 * @method static Builder|WhatsappBusinessAccount whereName($value)
 * @method static Builder|WhatsappBusinessAccount whereUpdatedAt($value)
 * @property-read Collection<int, WhatsappPhoneNumber> $whatsappPhoneNumbers
 * @property-read int|null $whatsapp_phone_numbers_count
 * @mixin Eloquent
 */
class WhatsappBusinessAccount extends Model
{
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    protected $table = 'whatsapp_business_accounts';
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
        'business_manager_account_id',
        'name',
        'is_using_public_test_number',
        'currency',
        'message_template_namespace',
    ];


    public function businessManagerAccount(): BelongsTo
    {
        return $this->belongsTo(BusinessManagerAccount::class, 'business_manager_account_id');
    }

    public function whatsappPhoneNumbers(): HasMany
    {
        return $this->hasMany(WhatsappPhoneNumber::class, 'whatsapp_business_account_id');
    }
}
