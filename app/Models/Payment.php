<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;
    const STATUS_PENDING = 'pending';
    // const STATUS_APPROVED = 'approved';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = "refunded";

    protected $table = 'payments'; // Replace with your actual table name

    protected $fillable = ['user_id','payment_id','transaction_id','status',
    'slug','payment_method_id','wallet_id','transaction_type','payment_status','response_message',
    'organization_id',
    'track_id','response_code','response_hash','card_brand','amount','currency','masked_pan','payment_type','invoice_file','type'];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }

    public function payable()
    {
        return $this->morphTo();
    }
    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function smsPlanTransaction()
    {
        return $this->hasOne(SmsPlanTransaction::class);
    }

    public function chargeRequestBank()
    {
        return $this->hasOne(ChargeRequestBank::class);
    }

    public function getInvoiceFileAttribute($value)
    {
        return $value != "" ?  asset($value) : $value;
    }
    public function getTypeAttribute($value)
    {
        return $value == 2 ? 'اسم المرسل'
            : 'شحن';
    }

}
