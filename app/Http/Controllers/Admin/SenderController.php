<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\announcement;
use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Sender;
use App\Models\Setting;
use App\Models\SmsConfiguration;
use App\Models\User;
use App\Repositories\SenderRepositoryInterface;
use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
use App\Services\Sms;
use App\Services\UrwayService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class SenderController extends SmsApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }
    protected $sendLoginNotification;
    protected $fileUploadService;
    protected $SenderRepository;
    protected $SendLoginNotification;
    protected $urwayService;

    public function __construct(
        SendLoginNotificationService $sendLoginNotification,
        FileUploadService $fileUploadService,
        SenderRepositoryInterface $SenderRepository,
        SendLoginNotificationService $SendLoginNotification,
        UrwayService $urwayService,
        SMS $sms
    ) {
        $this->sendLoginNotification = $sendLoginNotification;
        parent::__construct($sms);
        $this->SenderRepository = $SenderRepository;
        $this->SendLoginNotification = $SendLoginNotification;

        $this->urwayService = $urwayService;
        $this->fileUploadService = $fileUploadService;

    }

    public function index(Request $request)
    {
        if (isset($request->all) && $request->all == true) {
            if (isset($request->search)) {
                $search = $request->search;
                $items = Sender::where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('side_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_email', 'like', '%' . $search . '%')
                        ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                })
                    ->orderBy('created_at', 'DESC')->get();
            } else {
                $items = Sender::orderBy('created_at', 'DESC')->get();
            }
            return $this->response(true, 'items', $items);
        } else {
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });
            // Fetch paginated dataa
            if (isset($request->search)) {
                $search = $request->search;
                $items = Sender::where(function ($query) use ($search) {
                    $query->where('name', 'like', '%' . $search . '%')
                        ->orWhere('side_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_name', 'like', '%' . $search . '%')
                        ->orWhere('delegate_email', 'like', '%' . $search . '%')
                        ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                })
                    ->orderBy('created_at', 'DESC')->paginate($perPage);
            } else {
                $items = Sender::orderBy('created_at', 'DESC')->paginate($perPage);
            }
            // Customize the response
            return response()->json([
                'data' => $items->items(),
                'pagination' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ]);
        }
    }

    public function show($id)
    {

        $senders = $this->SenderRepository->findbyid($id);

        return $this->response(true, 'senders', $senders);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:14',
            'file_authorization_letter' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_commercial_register' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_value_added_tax_certificate' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_other' => 'mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'note' => 'nullable|string|max:255',

        ]);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $senderData = Sender::where(array('id' => $id))->first();
        if (empty($senderData)) {
            return $this->response(false, 'errors', __('message.lbl_sender_not_found'), 400);
        }
        $data = $request->all();

        if ($request->file('file_commercial_register')) {
            $data['file_commercial_register'] = $this->fileUploadService->upload($request->file('file_commercial_register'));
        }

        if ($request->file('file_commercial_register')) {
            $data['file_commercial_register'] = $this->fileUploadService->upload($request->file('file_commercial_register'));
        }

        if ($request->file('file_value_added_tax_certificate')) {
            $data['file_value_added_tax_certificate'] = $this->fileUploadService->upload($request->file_value_added_tax_certificate);
        }

        if (($request->file('file_authorization_letter'))) {
            $data['file_authorization_letter'] = $this->fileUploadService->upload($request->file('file_authorization_letter'));
        }
        if ($request->file('file_other')) {
            $data['file_other'] = $this->fileUploadService->upload($request->file('file_other'));
        }
        $sender = $this->SenderRepository->update($id, $data);
        $sender_same_name_and_accepted = ($sender->name == $request->name && $sender->status == 1);
        $user_id = auth('admin')->user()->id;
        $user = auth('api')->user();

        $event_description = "User #{$user_id} have update sender name {$request->name} ";
        Sender::logEventAudit('RequestSenderName', $event_description, 'Sender', $sender->id, $data, $user_id, 'Supervisor');

        return $this->response(true, 'senders', $sender);
    }

    public function destroy($id)
    {

        $Senders = Sender::where(array('id' => $id))->first();
        if ($Senders != null) {

            $this->SenderRepository->delete($id);
            return $this->response(true, __('message.msg_delete_row'));
        } else {
            return $this->response(false, 'errors', __('message.lbl_sender_not_found'), 400);
        }
    }

    public function updateDefault($id)
    {
        $sender = Sender::find($id);
        $updated = Sender::updateByArray(['default' => 0], ['user_id' => $sender->user_id]);
        // Update the user's 'default' attribute to 1 based on their ID
        $updated = Sender::updateByArray(['default' => 1], ['id' => $id]);
        if ($updated) {
            return $this->response(true, __('message.msg_update_row'));
        } else {
            return $this->response(false, __('message.msg_update_failed'));
        }
    }

    public function enable($senderId)
    {
        try {
            $sender = Sender::find($senderId);
            if ($sender->status == Sender::STATUS_APPROVED) {
                return $this->response(false, 'An error occurred', null, 500);
            }
            $sender->update(['status' => Sender::STATUS_APPROVED]);
            return $this->response(true, __('message.msg_update_row'));
        } catch (\Exception $e) {
            return $this->response(false, 'An error occurred', null, 500);
        }
    }

    public function disable($senderId)
    {
        try {
            $sender = Sender::find($senderId);
            if ($sender->status == Sender::STATUS_REJECTED) {
                return $this->response(false, 'An error occurred', null, 500);
            }
            $sender->update(['status' => Sender::STATUS_REJECTED]);
            return $this->response(true, __('message.msg_update_row'));
        } catch (\Exception $e) {
            return $this->response(false, 'An error occurred', null, 500);
        }
    }

    public function markAsWaitingPayment($senderId){
        try {
            $sender = Sender::find($senderId);
            if ($sender->status == Sender::STATUS_WAITING_FOR_PAYMENT) {
                return $this->response(false, 'An error occurred', null, 500);
            }
            $sender->update(['status' => Sender::STATUS_WAITING_FOR_PAYMENT]);
            return $this->response(true, __('message.msg_update_row'));
        } catch (\Exception $e) {
            return $this->response(false, 'An error occurred', null, 500);
        }
    }


    private function updateChannelStatus($senderId, $channelStatus, $smsStatus, $senderStatus)
    {
        try {

            $sender = Sender::find($senderId);
            if ($sender && $sender->status !== $senderStatus) {
                $sender->update(['status' => $senderStatus]);

                $smsConfig = SmsConfiguration::where('sender_id', $senderId)->first();
                if ($smsConfig && $smsConfig->status !== $smsStatus) {
                    $smsConfig->update(['status' => $smsStatus]);
                }
                $channel = Channel::where('connector_id', $smsConfig->connector_id)->first();
                if ($channel->status !== $channelStatus) {
                    $channel->update(['status' => $channelStatus]);
                }

                if ($channelStatus === 'active') {
                    $user = User::find($sender->user_id);
                    if ($user) {
                        $this->sendNotifications($user, $sender->name, 'sender_approval');
                    }
                }
            }

            return $this->response(true, __('message.msg_update_row'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->response(false, 'Sender not found', null, 404);
        } catch (\Exception $e) {
            return $this->response(false, 'An error occurred', null, 500);
        }
    }

    private function sendNotifications($user, $senderName, $type)
    {
        $announcement = Announcement::get_by_name($type);
        $siteName = Setting::get_by_name('site_name');
        $systemSmsSender = Setting::get_by_name('system_sms_sender');

        $params = [
            '{site_name}' => $siteName,
            'username' => $user->username,
            '{sender_name}' => $senderName,
        ];

        if (in_array($announcement->media, ['EMAIL', 'BOTH'])) {
            $subject = $announcement->title_ar . ' - ' . $announcement->title_en;
            $emailMessage = $this->replacePlaceholders($announcement->text_email, $params);

            $this->sendLoginNotification->sendEmailNotification(
                $user->email,
                $subject,
                'Dreams SMS',
                $emailMessage
            );
        }

        if (in_array($announcement->media, ['SMS', 'BOTH'])) {
            $smsMessage = $this->replacePlaceholders($announcement->text_sms, $params);
            $budget = $announcement->budget === 'ADMIN' ? 'admin' : 'user';
            $this->sendLoginNotification->sendSmsNotification($systemSmsSender, $user->number, $smsMessage, $budget, $budget === 'user' ? $user->id : null);
        }
    }

    private function replacePlaceholders($template, $params)
    {
        return str_replace(array_keys($params), array_values($params), $template);
    }

    public function updateInvoice(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'invoice_file' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $senderData = Sender::where(array('id' => $id))->first();
        if (empty($senderData)) {
            return $this->response(false, 'errors', __('message.lbl_sender_not_found'), 400);
        }
        $data = $request->all();

        if ($request->file('invoice_file')) {
            $data['invoice_file'] = $this->fileUploadService->upload($request->file('invoice_file'));
        }
        if ($senderData->status == 0) {
            $data['status'] = 3;
        }
        $sender = $this->SenderRepository->update($id, $data);
        $sender_same_name_and_accepted = ($sender->name == $request->name && $sender->status == 1);
        $user_id = auth('admin')->user()->id;
        $user = User::find($sender->user_id);

        $event_description = "تم رفع الفاتوره - بإنتظار السداد'";
        Sender::logEventAudit('RequestSenderName', $event_description, 'Sender', $sender->id, $data, $user_id, 'Supervisor');

        $this->sendNotifications($user, $sender->name, 'sender_payment');
        return $this->response(true, 'senders', $sender);
    }
    public function historySenderLogs($sender_id)
    {
        $auditLogs = (new AuditLog())->getAllWithUnion(['audit_log.entity_type' => 'Sender', 'audit_log.entity_id' => $sender_id]);
        return $this->response(true, 'auditLogs', $auditLogs);

    }

    public function addSenderNote(Request $request, $sender_id)
    {
        $note = $request->note;
        $supervisor = auth('admin')->user();
        $username = $supervisor->username;
        $created_by_type = 'Supervisor';
        $created_at = date('Y-m-d H:i:s');
        Sender::logEventAudit('SenderName', $note, 'Sender', $sender_id, [], $supervisor->id, 'Supervisor');
        $row = compact('note', 'username', 'created_by_type', 'created_at');
        return $this->response(true, 'row', $row);

    }



    public function deleteSelected(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:sender,id', // Ensure this matches your table name
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $ids = $request->input('ids');

        // Delete the records
        Sender::destroy($ids);

        return $this->response(true, __('message.msg_delete_row'));
    }

}
