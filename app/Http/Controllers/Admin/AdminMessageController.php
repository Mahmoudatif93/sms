<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminMessage;
use App\Models\AdminMessageDetails;
use App\Models\Outbox;
use App\Exports\MessagesExport;
use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ExportMessagesJob;
use App\Helpers\ExportSmsHelper;

class AdminMessageController extends SmsApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }

    protected $SendEmailNotification;
    protected $fileUploadService;
    public function __construct(
        FileUploadService $fileUploadService,
        SendLoginNotificationService $SendEmailNotification,
    ) {
        $this->SendEmailNotification        = $SendEmailNotification;
        $this->fileUploadService            = $fileUploadService;
    }
    public function index(Request $request)
    {
        $search = $request->get('search', null);
        $all = $request->get('all', false);
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $query = AdminMessage::where('deleted_by_user', 0)->with('details')
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('sender_name', 'like', '%' . $search . '%')
                        ->orWhere('count', 'like', '%' . $search . '%')
                        ->orWhere('text', 'like', '%' . $search . '%')
                        ->orWhere('status', 'like', '%' . $search . '%')
                        ->orWhere('channel', 'like', '%' . $search . '%')
                        ->orWhere('cost', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('details', function ($details) use ($search) {
                        $details->where('number', 'like', '%' . $search . '%');
                    });
            })->where('deleted_by_user', 0)->orderBy('id','DESC');


        if ($all) {
            // Fetch all items without pagination
            $items = $query->get()->map(function ($message) {
                return $this->transformMessage($message);
            });

            return $this->response(true, 'items', $items);
        } else {
            // Paginate items
            $items = $query->paginate($perPage);

            return response()->json([
                'data' => $items->getCollection()->map(function ($message) {
                    return $this->transformMessage($message);
                }),
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



    public function show($messageId)
    {

        // Fetch the message by message_id
        $message = AdminMessage::where('id', $messageId)->with('details')->first();
        return $this->response(true, 'message', $message);
    }



    public function destroy($messageId)
    {
        $data['deleted_by_user'] = 1;
        //Delete from outbox
        $outbox_info = outbox::get_message_by_message_id($messageId);
        if (!empty($outbox_info)) {
            outbox::where('id', $outbox_info->id)->delete();
        }
        $Message = AdminMessage::find($messageId);

        $Message->update($data);
        return $this->response(true, __('message.msg_delete_row'));
    }

    public function deleteSelected(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:message,id', // Ensure this matches your table name
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $ids = $request->input('ids');
        // Delete the records

        $data['deleted_by_user'] = 1;
        AdminMessage::whereIn('id', $ids)->update($data);

        $outbox_info = outbox::whereIn('message_id', $ids)->get();
        if (!empty($outbox_info)) {
            outbox::whereIn('message_id', $ids)->delete();
        }

        return $this->response(true, __('message.msg_delete_row'));
    }



    private function transformMessage($message)
    {
        return [
            'message' => $message->toArray()
        ];
    }


    /* public function exportMessages(Request $request, $messageId = null)
     {
         // Prepare the filters
         $filters = $request->only(['from_date', 'till_date', 'sender_name', 'search', 'number']);
         // Prepare the query
         $query        = $this->getExportQuery($filters,  $messageId, $request->exportType);
         $totalRecords = $query->count();
         $userEmail = auth('admin')->user()->email;
         $fileName     = $messageId === null
             ? 'Admin_messages_export_' . time() . '.xlsx'
             : 'Admin_messageDetails_export_' . time() . '.xlsx';
         $filePath = 'exports/' . $fileName;
         if ($totalRecords === 0) {
             return response()->json(['message' => 'No messages found for export.'], 404);
         }
         if ($totalRecords > 100000) {
             // If records exist, dispatch the job to export
             ExportMessagesJob::dispatch($filters, $workspaceId = null, $messageId, $userEmail, $request->exportType, 'admin', 'admin')
                 ->onQueue('exports'); // Make new Queue for export job
             return $this->response(true, 'Data exceeds 100,000 records. The file will be sent to your email..', 'email');
         }
         return Excel::download(new MessagesExport($query, 100000, $messageId, $request->exportType), $fileName);
     }*/

    public function exportMessages(Request $request, $messageId = null)
    {

        $filters = $request->only(['from_date', 'till_date', 'sender_name', 'search', 'number']);

        return ExportSmsHelper::exportMessages($this, $filters, $messageId, $request->exportType, 'admin','admin');

    }


    public function getExportQuery($filters, $workspaceId = null, $messageId, $exportType)
    {


        $search = $filters['search'] ?? null;
        if ($messageId === null) {
            return ($exportType === 'details')
                ? AdminMessageDetails::filter($filters)
                : AdminMessage::where('deleted_by_user', 0)->with('details')
                ->when(!empty($search), function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery->where('sender_name', 'like', '%' . $search . '%')
                            ->orWhere('count', 'like', '%' . $search . '%')
                            ->orWhere('text', 'like', '%' . $search . '%')
                            ->orWhere('status', 'like', '%' . $search . '%')
                            ->orWhere('channel', 'like', '%' . $search . '%')
                            ->orWhere('cost', 'like', '%' . $search . '%');
                    })
                        ->orWhereHas('details', function ($details) use ($search) {
                            $details->where('number', 'like', '%' . $search . '%');
                        });
                })->where('deleted_by_user', 0)->orderBy('id','DESC');
        } else {
            return AdminMessageDetails::where('message_id', $messageId)->filter($filters);
        }
    }
}
