<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Services\FileUploadService;
use App\Services\AuditLogService;

class Sender extends Model
{
    use HasFactory;

    protected $table = 'sender'; // Replace with your actual table name

    const STATUS_PENDING = 0;
    const STATUS_APPROVED = 1;
    const STATUS_REJECTED = 2;
    const STATUS_WAITING_FOR_PAYMENT = 3;
    const STATUS_PAYMENT_CONFIRMATION = 4;
    const STATUS_EXPIRED = 5;
    protected $fillable = [
        'user_id',
        'organization_id',
        'name',
        'side_name',
        'side_type',
        'type',
        'commercial_register',
        'unified_number',
        'is_sent_to_hawsabah',
        'status',
        'default',
        'date',
        'note',
        'file_proof_of_contract',
        'file_authorization_letter',
        'file_other',
        'is_hlr',
        'discount_on_ported_number',
        'max_sms_one_day',
        'invoice_file',
        'delegate_name',
        'delegate_email',
        'delegate_mobile',
        'expire_date',
        'sms_sent_before',
        'contract_expiration_date'
    ];

    protected $appends = ['status_text','is_sent_to_hawsabah_text','is_test','default_text'];
    public function getIsSentToHawsabahTextAttribute()
    {
        return $this->is_sent_to_hawsabah ? __('message.upload_successful') : __('message.upload_failed');
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public static function get_active_by_user_id($user_id, $search = null)
    {
        if ($search != null) {

            $results = Sender::where(array('user_id' => $user_id, 'status' => 1))
                ->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('side_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_email', 'like', '%' . $search . '%')
                        ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                })
                ->get();
        } else {

            $results = Sender::where(array('user_id' => $user_id, 'status' => 1))
                ->get();
        }
        return $results;
    }

    public function gateways()
    {
        return $this->belongsToMany(Gateway::class, 'gateway_sender');
    }


    public static function get_by_ids_user_id($user_id)
    {
        $res = array();
        $ids = user::where(array('id' => $user_id))->first()->granted_sender_ids;
        $parent_id = user::where(array('id' => $user_id))->first()->parent_id;
        if (!empty($ids)) {
            $entries = Sender::where(array('user_id' => $parent_id, 'status' => 1))
                ->whereIn('id', $ids)
                ->get();
        } else {
            $entries = Sender::where(array('user_id' => $parent_id, 'status' => 1))
                ->get();
        }

        foreach ($entries as $entry) {
            $entry['count'] = Contact::get_distinct_cnt_by_group_id($entry['id']);
            $res[$entry['id']] = $entry;
        }
        return $res;
    }

    public function getIsTestAttribute(){
        return $this->id == 0;
    }

    public function getDefaultTextAttribute(){
        if($this->id == 0){
            return "رسالة تجريبية من موقع dreams.sa";
        }
        return null;
    }
    public static function sender_name_count($user_id)
    {
        $count = Sender::where('status', 1)
            ->where('user_id', $user_id)
            ->count();
        return $count;
    }

    public function getStatusTextAttribute($value)
    {
        $statusMessages = [
            self::STATUS_PENDING => __('message.pending'),
            self::STATUS_APPROVED => __('message.approved'),
            self::STATUS_REJECTED => __('message.rejected'),
            self::STATUS_WAITING_FOR_PAYMENT => __('message.waiting_for_payment'),
            self::STATUS_PAYMENT_CONFIRMATION => __('message.payment_confirmation'),
            self::STATUS_EXPIRED => __('message.expired'),
        ];
        return $statusMessages[$this->status] ?? __('message.unknown');
    }

    public static function updateByArray(array $data, array $conditions)
    {
        return Sender::where($conditions)->update($data);
    }

    public function payment()
    {
        $this->updateByArray(array('status' => 4), array('id' => $this->id));
    }


    public function getInvoiceFileAttribute($value)
    {
        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }

    public function getFileAuthorizationLetterAttribute($value)
    {

        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }

    public function getFileCommercialRegisterAttribute($value)
    {

        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }


    public function getFileValueAddedTaxCertificateAttribute($value)
    {

        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }

    public function getFileOtherAttribute($value)
    {

        $fileUploadService = app(FileUploadService::class);
        return $value != "" ? $fileUploadService->getFileUrl($value) : $value;
    }

    public static function logEventAudit($event_type, $event_description, $entity_type, $entity_id, $changes = [], $created_by_id=0, $created_by_type="User")
    {
        AuditLogService::logEventAudit([
            'event_type' => $event_type,
            'event_description' => $event_description,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'changes' => $changes,
            'created_by_id' => $created_by_id,
            'created_by_type' => $created_by_type,
        ]);
    }
}
