<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Support\Facades\Validator;
use App\Models\Sender;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class SenderController extends BaseApiController
{
    protected $fileUploadService;
    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function store(Request $request)
    {
        $rules = [
            'organization_id' => 'required|exists:organizations,id',
            'name' => 'required|string|max:14',
            'type' => 'required',
            'delegate_name' => 'required',
            'delegate_email' => 'required|email',
            'delegate_mobile' => 'required',
            'file_authorization_letter' => 'required|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_other' => 'nullable|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'note' => 'nullable|string|max:255',
        ];

        $organization = Organization::findOrFail($request->organization_id);
        if($organization->commercial_registration_number == null || $organization->unified_number == null|| !$organization->hasMedia('organization-commercial-register')  || !$organization->hasMedia('organization-value-added-tax-certificate')){
            return $this->response(false, 'errors', __('message.organization_details_are_incomplete'), 400);
        }

     
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();

        $data['file_authorization_letter'] = $this->fileUploadService->upload($request->file_authorization_letter);
        $data['file_other'] = $this->fileUploadService->upload($request->file_other);

        $data['organization_id'] = $request->organization_id;  // Update with the authenticated user ID
        $data['name'] = $request->name;
        $data['side_name'] = $request->side_name;
        $data['side_type'] = $request->side_type;
        $data['type'] = $request->type;
        $data['commercial_register'] = $request->commercial_register;
        $data['delegate_name'] = $request->delegate_name;
        $data['delegate_email'] = $request->delegate_email;
        $data['max_sms_one_day'] = $request->max_sms_one_day;
        $data['note'] = $request->note;

        $sender = Sender::create($data);
        $event_description = "Orgnization #{$request->organization_id} have requested sender name {$request->name} ";
        Sender::logEventAudit('RequestSenderName', $event_description, 'Sender', $sender->id, $data, \Auth::user()->id, 'User');

        //TODO: send Notification
        // $this->SenderNotify($user, $sender, $sender_same_name_and_accepted);
        return $this->response(true, 'senders', $sender);
    }
}
