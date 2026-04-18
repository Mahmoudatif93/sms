<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'configuration',
        'min_amount',
        'max_amount',
        'supported_currencies',
        'processing_fee',
        'fee_type', // percentage or fixed
        'display_order',
        'description'
    ];

    protected $casts = [
        'configuration' => 'json',
        'is_active' => 'boolean',
        'supported_currencies' => 'array',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'processing_fee' => 'decimal:2',
        'display_order' => 'integer'
    ];

    // Payment method types
    const TYPE_VISA = 'visa';
    const TYPE_BANK_TRANSFER = 'bank_transfer';
    const TYPE_WALLET = 'wallet';

    // Fee types
    const FEE_TYPE_PERCENTAGE = 'percentage';
    const FEE_TYPE_FIXED = 'fixed';

    /**
     * Get all payments made using this payment method
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Scope to get only active payment methods
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get payment methods that support a specific currency
     */
    public function scopeSupportsCurrency(Builder $query, string $currency): Builder
    {
        return $query->where(function ($query) use ($currency) {
            $query->whereJsonContains('supported_currencies', $currency)
                  ->orWhereNull('supported_currencies');
        });
    }

    /**
     * Scope to get payment methods suitable for a given amount
     */
    public function scopeForAmount(Builder $query, float $amount): Builder
    {
        return $query->where(function ($query) use ($amount) {
            $query->where('min_amount', '<=', $amount)
                  ->orWhereNull('min_amount');
        })->where(function ($query) use ($amount) {
            $query->where('max_amount', '>=', $amount)
                  ->orWhereNull('max_amount');
        });
    }

    /**
     * Get available payment methods for a specific transaction
     */
    public static function getAvailableForTransaction(float $amount, string $currency): Builder
    {
        return static::active()
            ->supportsCurrency($currency)
            ->forAmount($amount)
            ->orderBy('display_order');
    }

    /**
     * Calculate processing fee for a given amount
     */
    public function calculateFee(float $amount): float
    {
        if ($this->fee_type === self::FEE_TYPE_PERCENTAGE) {
            return ($amount * $this->processing_fee) / 100;
        }
        
        return $this->processing_fee;
    }

    /**
     * Calculate total amount including processing fee
     */
    public function calculateTotalWithFee(float $amount): float
    {
        return $amount + $this->calculateFee($amount);
    }

    /**
     * Check if payment method supports a specific currency
     */
    public function supportsCurrencyCode(string $currency): bool
    {
        if (is_null($this->supported_currencies)) {
            return true;
        }

        return in_array(strtoupper($currency), $this->supported_currencies);
    }

    /**
     * Check if amount is within allowed limits
     */
    public function isAmountAllowed(float $amount): bool
    {
        if ($this->min_amount && $amount < $this->min_amount) {
            return false;
        }

        if ($this->max_amount && $amount > $this->max_amount) {
            return false;
        }

        return true;
    }

    /**
     * Get configuration value by key
     */
    public function getConfig(string $key, $default = null)
    {
        return data_get($this->configuration, $key, $default);
    }

    /**
     * Set configuration value
     */
    public function setConfig(string $key, $value): self
    {
        $config = $this->configuration ?? [];
        data_set($config, $key, $value);
        $this->configuration = $config;
        return $this;
    }

    /**
     * Check if payment method is bank transfer
     */
    public function isBankTransfer(): bool
    {
        return $this->code === self::TYPE_BANK_TRANSFER;
    }

    /**
     * Check if payment method is visa
     */
    public function isVisa(): bool
    {
        return $this->code === self::TYPE_VISA;
    }

    /**
     * Check if payment method is wallet
     */
    public function isWallet(): bool
    {
        return $this->code === self::TYPE_WALLET;
    }

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        // Set default display order when creating
        static::creating(function ($model) {
            if (is_null($model->display_order)) {
                $model->display_order = static::max('display_order') + 1;
            }
        });
    }
}