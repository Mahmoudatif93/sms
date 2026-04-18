<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 *
 * @property string $name
 * @property int $id
 * @property int $user_id
 * @property int $service_id
 * @property string $amount
 * @property string|null $currency
 * @property int|null $sms_point
 * @property string|null $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $system
 * @property string $currency_code
 * @property-read Service $service
 * @property-read Collection<int, WalletTransaction> $transactions
 * @property-read int|null $transactions_count
 * @property-read User $user
 * @method static Builder|Wallet newModelQuery()
 * @method static Builder|Wallet newQuery()
 * @method static Builder|Wallet query()
 * @method static Builder|Wallet whereAmount($value)
 * @method static Builder|Wallet whereCreatedAt($value)
 * @method static Builder|Wallet whereCurrency($value)
 * @method static Builder|Wallet whereId($value)
 * @method static Builder|Wallet whereServiceId($value)
 * @method static Builder|Wallet whereSmsPoint($value)
 * @method static Builder|Wallet whereStatus($value)
 * @method static Builder|Wallet whereUpdatedAt($value)
 * @method static Builder|Wallet whereUserId($value)
 * @mixin Eloquent
 */
class Wallet extends Model
{
    public $incrementing = false;
    /**
     * The primary key is a UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    protected $keyType = 'string';


    protected $table = 'wallets';

    protected $fillable = [
        'name',
        'user_id',
        'service_id',
        'amount',
        'pending_amount',
        'sms_point',
        'currency_code',
        'system',
        'status',
        'type'
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string)Str::uuid(); // Generate a UUID
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    // Define the inverse of the morph relationship
    public function paymentsender()
    {
        return $this->morphOne(Paymentsender::class, 'walletable');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WalletAssignment::class);
    }

    public function smsquotas(): HasMany
    {
        return $this->hasMany(SmsQuota::class,'wallet_id');
    }

    public function wallettable(): MorphTo
    {
        return $this->morphTo();
    }
    public function getAvailableAmountAttribute(): float
    {
        return (float) ($this->amount - $this->pending_amount);
    }

    public function hasSufficientFunds(float $cost): bool
    {
        return $this->available_amount >= $cost;
    }

}
