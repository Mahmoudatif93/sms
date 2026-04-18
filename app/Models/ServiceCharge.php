<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $user_id
 * @property string $payment_method
 * @property float $amount
 * @property string $status
 * @property string|null $transaction_id
 * @property int|null $sms_points
 * @property string|null $bank_transfer_proof
 * @property int|null $approved_by
 * @property int|null $processed_by
 * @property Carbon|null $payment_processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Service $service
 * @property-read User|null $approvedBy
 * @property-read User|null $processedBy
 */
class ServiceCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'user_id',
        'payment_method',
        'amount',
        'status',
        'transaction_id',
        'sms_points',
        'bank_transfer_proof',
        'approved_by',
        'processed_by',
        'payment_processed_at'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'sms_points' => 'integer',
        'payment_processed_at' => 'datetime'
    ];

    // Payment method constants
    const PAYMENT_VISA = 'visa';
    const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    // Status constants
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(related: Organization::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePendingBankTransfers($query)
    {
        return $query->where('payment_method', self::PAYMENT_BANK_TRANSFER)
                    ->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isBankTransfer(): bool
    {
        return $this->payment_method === self::PAYMENT_BANK_TRANSFER;
    }

    public function isVisa(): bool
    {
        return $this->payment_method === self::PAYMENT_VISA;
    }
}