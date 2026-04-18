<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\FileUploadService;

class ChargeRequestBank extends Model
{
    use HasFactory;
    protected $table = 'charge_request_bank'; // Replace with your actual table name
    const STATUS_PENDING = 0;
     const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;

    protected $fillable = [
        'user_id',
        'organization_id',
        'receipt_attach',
        'invoice_file',
        'paymentreceipt',
        'points_cnt',
        'amount',
        'currency',
        'bank_name',
        'account_number',
        'account_owner_name',
        'deposit_date',
        'request_date',
        'status',
        'type',
        'service_id'
    ];

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class,'service_id');
    }


    public function getInvoiceFileAttribute($value)
    {
        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }


    public function getReceiptAttachAttribute($value)
    {
        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }

    public function getTypeAttribute($value)
    {
        return $value == 2 ? 'اسم المرسل'
            : 'شحن';
    }
}
