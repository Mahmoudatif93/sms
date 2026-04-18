<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\WalletTransaction
 *
 * @property string $id
 * @property string $wallet_id
 * @property string $transaction_type
 * @property float $amount
 * @property string $status
 * @property string|null $description
 * @property array|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Wallet $wallet
 * @property-read Model|Eloquent $quotaable
 *
 * @method static Builder|WalletTransaction newModelQuery()
 * @method static Builder|WalletTransaction newQuery()
 * @method static Builder|WalletTransaction query()
 * @method static Builder|WalletTransaction whereId($value)
 * @method static Builder|WalletTransaction whereWalletId($value)
 * @method static Builder|WalletTransaction whereTransactionType($value)
 * @method static Builder|WalletTransaction whereAmount($value)
 * @method static Builder|WalletTransaction whereStatus($value)
 * @method static Builder|WalletTransaction whereDescription($value)
 * @method static Builder|WalletTransaction whereCreatedAt($value)
 * @method static Builder|WalletTransaction whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class WalletTransaction extends Model
{

    const WALLET_TRANSACTION_AI = 'ai';
    const WALLET_TRANSACTION_CHATBOT = 'chatbot';
    const WALLET_TRANSACTION_INBOX_AGENT = 'inbox_agent';
    const WALLET_TRANSACTION_HOSTING = 'hosting';
    const WALLET_TRANSACTION_WHATSAPP = 'whatsapp';
    

    protected $fillable = [
        'wallet_id',
        'transaction_type',
        'category',
        'amount',
        'status',
        'description',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'float',
    ];

    /**
     * Get the wallet that owns this transaction.
     *
     * @return BelongsTo
     */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Get the related quotaable model (morph).
     *
     * @return MorphTo
     */
    public function quotaable(): MorphTo
    {
        return $this->morphTo();
    }
}
