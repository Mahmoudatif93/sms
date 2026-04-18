<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Exports\MessagesExport;
use App\Models\Message;
use App\Models\Outbox;
use App\Models\MessageDetails;
use App\Services\FileUploadService;
use App\Services\SendLoginNotificationService;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\ExportMessagesJob;
use App\Helpers\ExportSmsHelper;

class MessageController extends SmsApiController implements HasMiddleware
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
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $fromDate = $request->get('from_date', null);
        $tillDate = $request->get('till_date', null);
        $senderName = $request->get('sender_name', null);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });
        $query = Message::where('deleted_by_user', 0)->with(['workspace.organization'])
            ->when(!empty($search), function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('sender_name', 'like', '%' . $search . '%')
                        ->orWhere('count', 'like', '%' . $search . '%')
                        ->orWhere('text', 'like', '%' . $search . '%')
                        ->orWhere('status', 'like', '%' . $search . '%')
                        ->orWhere('channel', 'like', '%' . $search . '%')
                        ->orWhere('cost', 'like', '%' . $search . '%');
                })
                    ->orWhereHas('workspace', function ($workspaceQuery) use ($search) {
                        $workspaceQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhereHas('organization', function ($organizationQuery) use ($search) {
                                $organizationQuery->where('name', 'like', '%' . $search . '%');
                            });
                    });
            })
            ->when(!empty($fromDate), function ($query) use ($fromDate) {
                $query->whereDate('creation_datetime', '>=', $fromDate);
            })
            ->when(!empty($tillDate), function ($query) use ($tillDate) {
                $query->whereDate('creation_datetime', '<=', $tillDate);
            })
            ->when(!empty($senderName), function ($query) use ($senderName) {
                $query->where('sender_name', 'like', '%' . $senderName . '%');
            })
            ->where('deleted_by_user', 0)->orderBy('id','DESC');
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




    public function show($messageId)
    {

        // Fetch the message by message_id
        $message = Message::where('id', $messageId)->with('MessageDetails')->first();
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
        $Message = Message::find($messageId);
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
        Message::whereIn('id', $ids)->update($data);

        $outbox_info = outbox::whereIn('message_id', $ids)->get();
        if (!empty($outbox_info)) {
            outbox::whereIn('message_id', $ids)->delete();
        }

        return $this->response(true, __('message.msg_delete_row'));
    }


    /**
     * Transform the message data.
     *
     * @param \App\Models\Message $message
     * @return array
     */
    private function transformMessage($message)
    {
        return [
            'message' => $message->toArray(),
            'workspace_name' => $message->workspace->name ?? null,
            'organization_name' => $message->workspace->organization->name ?? null,
        ];
    }



    public function exportMessages(Request $request, $messageId = null)
    {
        $filters = $request->only(['from_date', 'till_date', 'sender_name', 'search', 'number']);
        return ExportSmsHelper::exportMessages($this, $filters, $messageId, $request->exportType, 'admin', null);
    }

    public function getExportQuery($filters, $workspaceId = null, $messageId, $exportType)
    {
        $search = $filters['search'] ?? null;

        if ($messageId === null) {
            return ($exportType === 'details')
                ? MessageDetails::filter($filters)
                : Message::where('deleted_by_user', 0)
                ->with(['workspace.organization'])
                ->when(!empty($search), function ($query) use ($search) {
                    $query->where(function ($subQuery) use ($search) {
                        $subQuery->where('sender_name', 'like', '%' . $search . '%')
                            ->orWhere('count', 'like', '%' . $search . '%')
                            ->orWhere('text', 'like', '%' . $search . '%')
                            ->orWhere('status', 'like', '%' . $search . '%')
                            ->orWhere('channel', 'like', '%' . $search . '%')
                            ->orWhere('cost', 'like', '%' . $search . '%');
                    })
                        ->orWhereHas('workspace', function ($workspaceQuery) use ($search) {
                            $workspaceQuery->where('name', 'like', '%' . $search . '%')
                                ->orWhereHas('organization', function ($organizationQuery) use ($search) {
                                    $organizationQuery->where('name', 'like', '%' . $search . '%');
                                });
                        });
                })->where('deleted_by_user', 0)->orderBy('id','DESC');
        } else {
            return MessageDetails::where('message_id', $messageId)->filter($filters);
        }
    }
}
