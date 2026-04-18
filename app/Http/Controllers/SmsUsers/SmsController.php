<?php

namespace App\Http\Controllers\SmsUsers;

use App\Http\Responses\Channel;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Rules\ValidSender;
use App\Models\Outbox;
use App\Models\Message;
use App\Services\FileUploadService;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\MessageStatistic;
use App\Helpers\Sms\MessageValidationHelper;
use Illuminate\Validation\ValidationException;
use App\Class\SmsProcessorFactory;
use App\Helpers\Sms\MessageHelper;
use App\Http\Controllers\SmsApiController;
use App\Services\Sms;
use App\Traits\WalletManager;
use App\Enums\Service as EnumService;
use App\Models\Service as MService;
use App\Models\MessageDetails;
use App\Services\Sms\SmsProcessingService;
use App\Helpers\ExportSmsHelper;


class SmsController extends SmsApiController implements HasMiddleware
{
    use WalletManager;
    protected $fileUploadService;
    protected $sms;
    protected $smsProcessingService;

    public function __construct(FileUploadService $fileUploadService, Sms $sms, SmsProcessingService $smsProcessingService)
    {
        $this->fileUploadService = $fileUploadService;
        $this->sms = $sms;
        $this->smsProcessingService = $smsProcessingService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspace}/sms/messages",
     *     summary="Get sms messages sent report",
     *     tags={"SMS"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Retrieve all records without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="from_date",
     *         in="query",
     *         description="Start date for filtering",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="till_date",
     *         in="query",
     *         description="End date for filtering",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sender_name",
     *         in="query",
     *         description="Filter by sender name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items()),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer")
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request, Workspace $workspace)
    {
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        $query = Message::where('workspace_id', $workspace->id)->where('deleted_by_user', 0);
        if (!empty($request->from_date)) {
            $query->where('creation_datetime', '>=', $request->from_date);
        }

        if (!empty($request->to_date)) {
            $query->where('creation_datetime', '<=', $request->to_date);
        }

        if (!empty($request->sender_name)) {
            $query->where('sender_name', $request->sender_name);
        }
        if (!empty($request->search)) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('sender_name', 'like', '%' . $search . '%')
                    ->orWhere('text', 'like', '%' . $search . '%');
            });
        }
        $query->orderBy('id','DESC');
        $messages = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $messages->getCollection()->map(function ($message) {
            return new \App\Http\Responses\Message($message);
        });
        $messages->setCollection($response);
        return $this->paginateResponse(true, 'sms retrieved successfully', $messages);

    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspace}/sms/messages/{message}",
     *     summary="Get a sent message",
     *     description="Get a specific sms message by its ID",
     *     tags={"SMS"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the message to delete",
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Message not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */
    public function show(Request $request, Workspace $workspace, Message $message)
    {
        if ($message->workspace_id !== $workspace->id) {
            return $this->response(false, 'errors', ['sms' => 'Unauthorized access'], 403);
        }

        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        $query = MessageDetails::with('message')->where('message_id', $message->id);
        if (!empty($request->search)) {
            $search = $request->search;
            $query->where(function ($query) use ($search) {
                $query->where('number', 'like', '%' . $search . '%')
                    ->orWhere('text', 'like', '%' . $search . '%');
            });
        }
        $messages = $query->paginate($perPage, ['*'], 'page', $page);

        $response = $messages->getCollection()->map(function ($message) {
            return new \App\Http\Responses\MessageDetails($message);
        });
        $messages->setCollection($response);
        return $this->paginateResponse(true, 'Webhooks retrieved successfully', $messages);

    }

    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspace}/sms/messages/{message}",
     *     summary="Delete a sent message",
     *     description="Deletes a specific sent message by its ID",
     *     operationId="destroySentMessage",
     *     tags={"Messages Sent Report"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the message to delete",
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Message not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Message not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */

    public function destroy(Workspace $workspace, Message $message)
    {
        if ($message->workspace_id !== $workspace->id) {
            return $this->response(false, 'errors', ['sms' => 'Unauthorized access'], 403);
        }
        $outbox = Outbox::where('message_id', $message->id)->first();
        //Delete from outbox
        if (!empty($outbox)) {
            Outbox::where('id', $outbox->id)->delete();
        }
        $message->delete();
        return $this->response(true, __('message.msg_delete_row'));
    }

    /**
     * @OA\Delete(
     *     path="/api/workspaces/{workspace}/sms/messages/{message}/bulk-delete",
     *     summary="Delete selected sent messages",
     *     description="Deletes multiple sent messages by their IDs",
     *     operationId="deleteSelectedSentMessages",
     *     tags={"Messages Sent Report"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), description="Array of message IDs to delete")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Messages deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={
     *         {"api_key": {}}
     *     }
     * )
     */

    public function deleteSelected(Workspace $workspace, Request $request)
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
        $messagesCount = Message::whereIn('id', $ids)
            ->where('workspace_id', $workspace->id)
            ->count();
        if ($messagesCount !== count($ids)) {
            return $this->response(false, 'error', 'Unauthorized access to messages from different workspace', 403);
        }

        // Delete the records
        $outbox_info = outbox::whereIn('message_id', $ids)->get();
        if (!empty($outbox_info)) {
            outbox::whereIn('message_id', $ids)->delete();
        }
        Message::whereIn('id', $ids)->delete();
        return $this->response(true, __('message.msg_delete_row'));
    }


    public function export(Request $request, Workspace $workspace, Message $message)
    {
        if (!empty($message->toArray()) && ($message->workspace_id !== $workspace->id)) {
            return $this->response(false, 'errors', ['sms' => 'Unauthorized access'], 403);
        }
        $filters = $request->only(['from_date', 'till_date', 'sender_name', 'search', 'number']);
        return ExportSmsHelper::exportMessages($this, $filters, $message->id, $request->exportType, null, $workspace->id);
    }

    public function getExportQuery($filters, $workspaceId, $messageId, $exportType)
    {
        if ($messageId === null) {
            return ($exportType === 'details')
                ? MessageDetails::filter($filters, $workspaceId)
                : Message::where('workspace_id', $workspaceId)->orderBy('id','DESC')->filter($filters);
        } else {
            return MessageDetails::where('message_id', $messageId)->filter($filters, $workspaceId);
        }
    }
    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspace}/channels/{channel}/statistics",
     *     summary="Generate statistics Message",
     *     tags={"Messages"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="all_numbers", type="string", description="All numbers or special identifiers"),
     *             @OA\Property(property="message", type="string", description="Message content"),
     *             @OA\Property(property="send_time_method", type="string", enum={"NOW", "LATER"}, description="Send time method"),
     *             @OA\Property(property="send_time", type="string", format="date-time", description="Send time if method is LATER"),
     *             @OA\Property(property="sms_type", type="string", enum={"NORMAL", "VARIABLES","CALENDAR","ADS"}, description="SMS type"),
     *             @OA\Property(property="repeation_times", type="integer", description="Repetition times, if applicable"),
     *             @OA\Property(property="excle_file", type="string", description="Excel file path, if all_numbers is excel_file"),
     *             @OA\Property(property="calendar_time", type="string", format="date-time", description="Calendar time if sms_type is CALENDAR"),
     *             @OA\Property(property="reminder", type="integer", description="Reminder if sms_type is CALENDAR"),
     *             @OA\Property(property="reminder_text", type="string", description="Reminder text if sms_type is CALENDAR"),
     *             @OA\Property(property="location_url", type="string", description="Location URL (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *              @OA\Property(property="message_id", type="integer", example=1),
     *                 @OA\Property(property="entries", type="array",
     *                     @OA\Items(type="object",
     *                         @OA\Property(property="id", type="integer", example=966),
     *                         @OA\Property(property="name_ar", type="string", example="السعودية"),
     *                         @OA\Property(property="name_en", type="string", example="Saudi Arabia"),
     *                         @OA\Property(property="coverage_status", type="integer", example=1),
     *                         @OA\Property(property="cnt", type="integer", example=5),
     *                         @OA\Property(property="cost", type="integer", example=5),
     *                     )
     *                 ),
     *                @OA\Property(property="count", type="integer", example=5),
     *                @OA\Property(property="cost", type="integer", example=10),
     *                @OA\Property(property="can_send", type="boolean", example=true)

     *             )
     *         )
     * )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="errors", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function statisticsV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'all_numbers' => 'required',
            'from' => ['required', 'string', new ValidSender($request->owner_id ?? $request->user_id)],
            'message' => 'required|string',
            'send_time_method' => 'required|in:NOW,LATER',
            'send_time' => 'required_if:send_time_method,LATER|date',
            'sms_type' => 'required|in:NORMAL,VARIABLES,CALENDAR,ADS',
            'repeation_times' => 'sometimes|numeric',
            'excle_file' => 'required_if:all_numbers,excel_file|string',
            'calendar_time' => 'required_if:sms_type,CALENDAR|date',
            'reminder' => 'required_if:sms_type,CALENDAR|integer',
            'reminder_text' => 'required_if:sms_type,CALENDAR|string',
            'location_url' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        $data = $request->all();
        $user = auth()->user();
        $data['message'] = decodeUnicodeEscape($data['message']);
        $data['user_id'] = $user->id;
        $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
        try {
            MessageValidationHelper::validateBadWords($data['message']);
            $data['message'] = MessageHelper::calanderMessage($data['message'], $data['sms_type'], $user->id, $data['from'], $request->calendar_time, $request->reminder, $request->reminder_text, $request->location_url);
        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        }


        $all_numbers = $this->sms->processNumbers($request['all_numbers']);
        $entries = [];
        $numberArr = [];
        $this->sms->processAllNumbers($all_numbers, $data['excle_file'] ?? null, $entries, $data['leng'], $numberArr, $data['message'], $data['sms_type'], $user);

        $data['all_numbers_json'] = json_encode($numberArr);
        $data['entries'] = array_values($entries);
        $data['count'] = array_reduce($data['entries'], function ($carry, $entry) {
            return $carry + $entry['cnt'];
        }, 0);
        $data['cost'] = array_reduce($data['entries'], function ($carry, $entry) {
            return $carry + $entry['cost'];
        }, 0);
        if ($data['count'] > 100001) {
            return $this->response(false, trans('message.msg_error_exceeded_maximum_number', ['number' => 100000]), [], 403);
        }

        $message = MessageStatistic::create($data);
        if ($data['cost'] <= 0) {
            $msg = 'error';
        } else {
            $msg = trans('message.ok');
        }


        return $this->response($data['cost'] > 0, $msg, ['message_id' => $message->id, 'entries' => $data['entries'], 'count' => $data['count'], 'cost' => $data['cost'], 'can_send' => $data['cost'] > 0], 200);
    }

    public function statistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'all_numbers' => 'required',
            'from' => ['required', 'string', new ValidSender($request->workspace->organization_id)],
            'message' => 'required|string',
            'send_time_method' => 'required|in:NOW,LATER',
            'send_time' => 'required_if:send_time_method,LATER|date',
            'sms_type' => 'required|in:NORMAL,VARIABLES,CALENDAR,ADS',
            'repeation_times' => 'sometimes|numeric',
            'excle_file' => 'required_if:all_numbers,excel_file|string',
            'calendar_time' => 'required_if:sms_type,CALENDAR|date',
            'reminder' => 'required_if:sms_type,CALENDAR|integer',
            'reminder_text' => 'required_if:sms_type,CALENDAR|string',
            'location_url' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        try {
            $data = $request->all();
            $user = auth()->user();
            $data['user_id'] = $user->id;

            // Check if this is a large dataset that requires background processing
            $estimatedCount = $this->smsProcessingService->estimateNumberCount(
                $data['all_numbers'],
                $data['sms_type'],
                $data['excle_file'] ?? null
            );
            // Define threshold for background processing (configurable)
            $backgroundProcessingThreshold = config('sms.background_processing_threshold', 10000);

            if ($estimatedCount > $backgroundProcessingThreshold) {
                // Process in background for large datasets
                return $this->processStatisticsInBackground($data, $user);
            } else {
                // Process immediately for small datasets
                $processedData = $this->smsProcessingService->processRequest($data, $user);
                return $this->response(
                    $processedData['cost'] > 0,
                    $processedData['cost'] > 0 ? trans('message.ok') : 'error',
                    [
                        'message_id' => 0,
                        'entries' => $processedData['entries'],
                        'count' => $processedData['count'],
                        'cost' => $processedData['cost'],
                        'can_send' => $processedData['cost'] > 0,
                        'processing_type' => 'immediate'
                    ],
                    200
                );
            }
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        }
    }



    /**
     * Process statistics in background for large datasets
     */
    private function processStatisticsInBackground($data, $user)
    {
        // Create a StatisticsProcessing record
        $processingId = \App\Models\StatisticsProcessing::generateProcessingId();

        $statisticsProcessing = \App\Models\StatisticsProcessing::create([
            'processing_id' => $processingId,
            'user_id' => $user->id,
            'workspace_id' => $data['workspace']->id ?? null,
            'all_numbers' => $data['all_numbers'],
            'sender_name' => $data['from'],
            'message' => $data['message'],
            'send_time_method' => $data['send_time_method'],
            'send_time' => $data['send_time'] ?? null,
            'sms_type' => $data['sms_type'],
            'repeation_times' => $data['repeation_times'] ?? null,
            'excel_file' => $data['excle_file'] ?? null,
            'message_length' => calc_message_length($data['message'], $data['sms_type']),
            'status' => \App\Models\StatisticsProcessing::STATUS_PENDING
        ]);

        // Dispatch the background job
        \App\Jobs\ProcessStatisticsJob::dispatch($statisticsProcessing, $user)
            ->onQueue('statistics');

        return $this->response(
            true,
            trans('message.statistics_processing_started'),
            [
                'processing_id' => $processingId,
                'status' => 'processing',
                'message' => 'Statistics processing started in background. You will be notified when complete.',
                'processing_type' => 'background'
            ],
            202 // Accepted
        );
    }

    /**
     * Check the status of background statistics processing
     */
    public function checkStatisticsStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'processing_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $user = auth()->user();
        $processingId = $request->processing_id;

        $statisticsProcessing = \App\Models\StatisticsProcessing::where('processing_id', $processingId)
            ->where('user_id', $user->id)
            ->first();

        if (!$statisticsProcessing) {
            return $this->response(false, 'Processing record not found', [], 404);
        }

        $response = [
            'processing_id' => $processingId,
            'status' => $statisticsProcessing->status,
            'progress_percentage' => $statisticsProcessing->getProgressPercentage(),
            'total_numbers' => $statisticsProcessing->total_numbers,
            'processed_numbers' => $statisticsProcessing->processed_numbers,
            'started_at' => $statisticsProcessing->started_at,
            'completed_at' => $statisticsProcessing->completed_at
        ];

        if ($statisticsProcessing->isCompleted()) {
            $response['entries'] = $statisticsProcessing->entries_json;
            $response['count'] = $statisticsProcessing->processed_numbers;
            $response['cost'] = $statisticsProcessing->total_cost;
            $response['can_send'] = $statisticsProcessing->total_cost > 0;
        }

        if ($statisticsProcessing->isFailed()) {
            $response['error_message'] = $statisticsProcessing->error_message;
        }

        return $this->response(true, 'Status retrieved successfully', $response, 200);
    }

    /**
     * Approve or reject the statistics processing result
     */
    public function approveStatistics(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'processing_id' => 'required|string',
            'action' => 'required|in:approve,reject'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $user = auth()->user();
        $processingId = $request->processing_id;
        $action = $request->action;

        $statisticsProcessing = \App\Models\StatisticsProcessing::where('processing_id', $processingId)
            ->where('user_id', $user->id)
            ->where('status', \App\Models\StatisticsProcessing::STATUS_COMPLETED)
            ->first();

        if (!$statisticsProcessing) {
            return $this->response(false, 'Processing record not found or not completed', [], 404);
        }

        try {
            if ($action === 'approve') {
                $statisticsProcessing->approve($user->id);

                // Create MessageStatistic record for sending
                $messageStatistic = \App\Models\MessageStatistic::create($statisticsProcessing->toMessageStatistic());

                return $this->response(
                    true,
                    'Statistics approved successfully',
                    [
                        'message_id' => $messageStatistic->id,
                        'entries' => $statisticsProcessing->entries_json,
                        'count' => $statisticsProcessing->processed_numbers,
                        'cost' => $statisticsProcessing->total_cost,
                        'can_send' => true
                    ],
                    200
                );
            } else {
                $statisticsProcessing->reject($user->id);

                // إرسال إشعار للمستخدم برفض الإرسالية
                $this->sendUserRejectionNotification($statisticsProcessing, $user);

                return $this->response(
                    true,
                    'Statistics rejected successfully',
                    ['processing_id' => $processingId, 'status' => 'rejected'],
                    200
                );
            }
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 500);
        }
    }

    public function sendV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'all_numbers' => 'required',
            'from' => ['required', 'string', new ValidSender($request->workspace->organization_id)],
            'workspace' => 'required',
            'message' => 'required|string',
            'send_time_method' => 'required|in:NOW,LATER',
            'send_time' => 'required_if:send_time_method,LATER|date',
            'sms_type' => 'required|in:NORMAL,VARIABLES,CALENDAR,ADS',
            'repeation_times' => 'sometimes|numeric',
            'excle_file' => 'required_if:all_numbers,excel_file|string',
            'calendar_time' => 'required_if:sms_type,CALENDAR|date',
            'reminder' => 'required_if:sms_type,CALENDAR|integer',
            'reminder_text' => 'required_if:sms_type,CALENDAR|string',
            'location_url' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), statusCode: 422);
        }
        try {
            $data = $request->all();
            $user = auth()->user();
            $processedData = $this->smsProcessingService->processRequest($data, $user);


            $wallet = $this->getObjectWallet(
                $data['workspace'],
                MService::where('name', EnumService::SMS)->value('id'),
                \Auth::user()->id
            );

            if (!$wallet) {
                return $this->response(false, "Wallet not found", [], 403);
            }
            if (!$this->changeBalance($wallet, -1 * $processedData['cost'], "sms", "Send SMS Campaign")) {
                $this->fileUploadService->deleteFileOss($processedData["excle_file"] ?? null);
                return $this->response(false, trans('message.msg_error_insufficient_balance'), [], 403);
            }
            $isReviewMessage = MessageHelper::isReviewMessage(
                $processedData['message'],
                $processedData['from'] ?? $processedData['sender_name'],
                $user->isAllowUrl(),
                $user->isAllowSendBlock(),
                $processedData['count']
            );
            $message = $this->smsProcessingService->createMessage($processedData, $user, $isReviewMessage);
            return $this->response(
                true,
                trans('message.msg_send_successfully'),
                ['message_id' => $message->id],
                200
            );
        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/workspaces/{workspace}/channels/{channel}/messages",
     *     summary="Send a message",
     *     description="Sends a message and performs various validations.",
     *     tags={"Messages"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"message_id"},
     *             @OA\Property(property="message_id", type="integer", example=1, description="ID of the message statistic")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Message sent successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="message_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Validation or balance error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation or balance error"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function sendOrginal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message_id' => 'required|exists:message_statistics,id',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $message_statistic = MessageStatistic::findOrFail($request->message_id);
        $user = auth()->user();

        if ($user->check_sender_empty()) {
            $message_statistic->sender_name = 'Dreams';
            $message_statistic->message = trans('message.msg_test_sms');
        }

        try {
            MessageValidationHelper::validateBadWords($message_statistic->message);
            MessageValidationHelper::validateAdsTimeSend($message_statistic->sender_name, $message_statistic->send_time_method, $message_statistic->send_time);
        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        }

        $isReviewMessage = MessageHelper::isReviewMessage($message_statistic->message, $message_statistic->sender_name, $user->isAllowUrl(), $user->isAllowSendBlock(), $message_statistic->count);

        $isReviewFromParent = MessageHelper::isReviewFromParent($user->id);
        if ($user->isBalanceExpired()) {
            return $this->response(false, trans('message.msg_error_expired_blance'), [], 403);
        }

        if ($message_statistic->repeation_period <> null) {
            $this->fileUploadService->deleteFileOss($message_statistic->excle_file);
        }


        $response = \DB::transaction(function () use ($user, $message_statistic, $isReviewMessage, $isReviewFromParent) {
            if (!$user->changeBalance((-1 * $message_statistic->cost), "Send SMS Campaign", null, null, 0, \App\Enums\BalanceLogStatus::ACTIVE, 0)) {
                $this->fileUploadService->deleteFileOss($message_statistic->excle_file);
                return ['status' => false, 'msg' => trans('message.msg_error_insufficient_balance'), 'message_id' => -1];
            }

            $data = [
                'channel' => 'DIRECT',
                'user_id' => $user->id,
                'text' => $message_statistic->message,
                'count' => $message_statistic->count,
                'cost' => $message_statistic->cost,
                'length' => $message_statistic->leng,
                'creation_datetime' => \Carbon\Carbon::now(),
                'sending_datetime' => $message_statistic->send_time,
                'repeation_period' => 0, //TODO: Handle repetition period
                'repeation_times' => 0, //TODO: Handle repetition times
                'variables_message' => $message_statistic->sms_type == "VARIABLES" ? 1 : 0,
                'sender_name' => $message_statistic->sender_name,
                'excel_file_numbers' => $message_statistic->excle_file,
                'all_numbers' => $message_statistic->all_numbers_json,
                'encrypted' => $message_statistic->sms_type == "ADS" ? 1 : 0,
                'auth_code' => randomAuthCode(),
                'advertising' => $isReviewFromParent == 1 ? 2 : ($isReviewMessage == 1 ? 1 : 0), // 0=>default, 1=>review message, 2=>review message from parent,3=>approved message
                'sent_cnt' => 0,
                'lang' => MessageHelper::calcMessageLang($message_statistic->message),
            ];
            $outbox = Outbox::create($data);
            $data['encrypted'] = 0;
            $message = Message::create($data);
            $outbox->message_id = $message->id;
            $outbox->save();
            //TODO: chane name
            $processor = SmsProcessorFactory::createProcessor($message_statistic->count);
            if (!$processor->process($outbox)) {
                //TODO: roolback
                return ['status' => false, 'msg' => "Message details insert failed", 'message_id' => -1];
            }
            if ($isReviewFromParent) {
                MessageHelper::SendReviewMessageToParent();
            } elseif ($isReviewMessage) {
                MessageHelper::SendReviewMessage();
            }


            $this->fileUploadService->deleteFileOss($message_statistic->excle_file);

            return ['status' => true, 'msg' => trans('message.msg_send_successfully'), 'processor' => $processor, 'message_id' => $message->id];
        });

        if ($response['status']) {
            $processor = $response['processor'];
            //old
            // if ($message_statistic->send_time_method == 'NOW' && !$isReviewMessage && $message_statistic->sms_type != "VARIABLES") {
            //     $processor->sendMessage($response['message_id']);
            // }
            if ($message_statistic->send_time_method == 'NOW' && !$isReviewMessage && $message_statistic->sms_type != "VARIABLES" && !$isReviewFromParent) {
                $processor->sendMessage($response['message_id']);
            }
        }



        $message_statistic->delete();
        return $this->response($response['status'], $response['msg'], ['message_id' => $response['message_id']], 200);
    }


    public function sendNow(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'all_numbers' => 'required',
            'from' => ['required', 'string', new ValidSender($request->owner_id)],
            'workspace' => 'required',
            'message' => 'required|string',
            'send_time_method' => 'required|in:NOW,LATER',
            'send_time' => 'required_if:send_time_method,LATER|date',
            'sms_type' => 'required|in:NORMAL,VARIABLES,CALENDAR,ADS',
            'repeation_times' => 'sometimes|numeric',
            'excle_file' => 'required_if:all_numbers,excel_file|string',
            'calendar_time' => 'required_if:sms_type,CALENDAR|date',
            'reminder' => 'required_if:sms_type,CALENDAR|integer',
            'reminder_text' => 'required_if:sms_type,CALENDAR|string',
            'location_url' => 'sometimes'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $data = $request->all();
        $data['message'] = decodeUnicodeEscape($data['message']);
        $data = $request->all();
        $data['user_id'] = $request->owner_id;
        $user = \App\Models\User::where('id', $data['user_id'])->first();
        $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
        try {
            MessageValidationHelper::validateBadWords($data['message']);
            MessageValidationHelper::validateAdsTimeSend($data['from'], $data['send_time_method'], $data['send_time'] ?? null);
            $data['message'] = MessageHelper::calanderMessage($data['message'], $data['sms_type'], $request->owner_id, $data['from'], $request->calendar_time, $request->reminder, $request->reminder_text, $request->location_url);
        } catch (ValidationException $e) {
            return $this->response(false, $e->getMessage(), [], 403);
        }


        $all_numbers = $this->sms->processNumbers($request['all_numbers']);
        $entries = [];
        $numberArr = [];
        $this->sms->processAllNumbers($all_numbers, $data['excle_file'] ?? null, $entries, $data['leng'], $numberArr, $data['message'], $data['sms_type'], $user);

        $data['all_numbers_json'] = json_encode($numberArr);
        $data['entries'] = array_values($entries);
        $data['count'] = array_reduce($data['entries'], function ($carry, $entry) {
            return $carry + $entry['cnt'];
        }, 0);
        $data['cost'] = array_reduce($data['entries'], function ($carry, $entry) {
            return $carry + $entry['cost'];
        }, 0);


        if ($user->check_sender_empty()) {
            $data['from'] = 'Dreams';
            $data['message'] = trans('message.msg_test_sms');
        }

        if ($data['count'] > 100001) {
            return $this->response(false, trans('message.msg_error_exceeded_maximum_number', ['number' => 100000]), [], 403);
        }
        $isReviewMessage = MessageHelper::isReviewMessage($data['message'], $data['from'], $user->isAllowUrl(), $user->isAllowSendBlock(), $data['count']);
        $response = \DB::transaction(function () use ($user, $data, $isReviewMessage) {
            $wallet = $this->getObjectWallet($data['workspace'], MService::where('name', EnumService::SMS)->value('id'), \Auth::user()->id);
            if (!$wallet) {
                return ['status' => false, 'msg' => "Wallet not found", 'message_id' => -1];
            }
            if (!$this->changeBalance($wallet, -1 * $data['cost'], "sms", "Send SMS Campaign")) {
                $this->fileUploadService->deleteFileOss($data["excle_file"] ?? null);
                return ['status' => false, 'msg' => trans('message.msg_error_insufficient_balance'), 'message_id' => -1];
            }

            $data = [
                'channel' => 'DIRECT',
                'user_id' => $user->id,
                'text' => $data["message"],
                'count' => $data["count"],
                'cost' => $data["cost"],
                'length' => $data["leng"],
                'creation_datetime' => \Carbon\Carbon::now(),
                'sending_datetime' => $data["send_time"] ?? null,
                'repeation_period' => 0, //TODO: Handle repetition period
                'repeation_times' => 0, //TODO: Handle repetition times
                'variables_message' => $data["sms_type"] == "VARIABLES" ? 1 : 0,
                'sender_name' => $data["from"],
                'excel_file_numbers' => $data["excle_file"] ?? null,
                'all_numbers' => $data["all_numbers_json"],
                'encrypted' => $data["sms_type"] == "ADS" ? 1 : 0,
                'auth_code' => randomAuthCode(),
                'advertising' => $isReviewMessage == 1 ? 1 : 0, // 0=>default, 1=>review message, 2=>review message from parent,3=>approved message
                'sent_cnt' => 0,
                'lang' => MessageHelper::calcMessageLang($data["message"]),
            ];
            $outbox = Outbox::create($data);
            $data['encrypted'] = 0;
            $message = Message::create($data);
            $outbox->message_id = $message->id;
            $outbox->save();
            $processor = SmsProcessorFactory::createProcessor($data['count']);
            if (!$processor->process($outbox)) {
                //TODO: roolback
                return ['status' => false, 'msg' => "Message details insert failed", 'message_id' => -1];
            }
            if ($isReviewMessage) {
                MessageHelper::SendReviewMessage();
            }
            $this->fileUploadService->deleteFileOss($data['excle_file'] ?? null);
            return ['status' => true, 'msg' => trans('message.msg_send_successfully'), 'processor' => $processor, 'message_id' => $message->id];
        });

    }
    /**
     * @OA\Post(
     *     path="/api/SmsUsers/sms/upload",
     *     summary="Upload excel file",
     *     description="Upload a file to the server",
     *     operationId="uploadFile",
     *     tags={"SMS"},
     *     @OA\RequestBody(
     *         required=true,
     *          @OA\JsonContent(
     *              required={"file","sms_type" },
     *              @OA\Property(property="file", type="file"),
     *              @OA\Property(property="sms_type", type="string", enum={"NORMAL", "VARIABLES"}, description="SMS type"),
     *          )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                  @OA\Property(property="path", type="string")
     *              )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="unable to upload file",
     *         @OA\JsonContent(
     *             @OA\Property(property="false", type="boolean"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="errors", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="message", type="string", example="Invalid parameter")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function upload_excel(Workspace $workspace, Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx|max:10240',
            'sms_type' => 'required|in:NORMAL,VARIABLES',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        $path = $this->fileUploadService->upload($request->file('file'));
        if ($path) {
            if ($request->sms_type == "VARIABLES") {
                $fileExsit = $this->fileUploadService->getFileOss($path);
                if ($fileExsit) {
                    $arr = [];
                    $rows = \Spatie\SimpleExcel\SimpleExcelReader::create($path, 'xlsx')->noHeaderRow()->take(5)->getRows();
                    $rows->each(function (array $rowProperties) use (&$arr) {
                        $row = [];
                        for ($col = 0; $col < 26; $col++) {
                            if (!empty($rowProperties[$col])) {
                                array_push($row, $rowProperties[$col]);
                            }
                        }
                        $arr[] = (object) $row;
                    });
                    $success = trans('message.upload_successful');
                    return $this->response(true, $success, ['path' => $path, 'data' => $arr], 200);
                } else {
                    return $this->response(false, __('message.upload_failed'), null, 200);
                }

            } else {
                $success = trans('message.upload_successful');
                return $this->response(true, $success, ['path' => $path,], 200);
            }

        }
        return $this->response(false, 'File upload failed', null, 200);
    }

    /**
     * إرسال إشعار للمستخدم برفض الإرسالية
     */
    private function sendUserRejectionNotification($statisticsProcessing, $user): void
    {
        try {
            // إشعار النظام
            if (config('sms.notifications.enabled', true)) {
                $user->notify(new \App\Notifications\StatisticsProcessingCompleted($statisticsProcessing));
            }

            // إشعار SMS للمستخدم
            $this->sendSmsRejectionNotification($statisticsProcessing, $user);

        } catch (\Exception $e) {
            \Log::warning("Failed to send rejection notification", [
                'processing_id' => $statisticsProcessing->processing_id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * إرسال إشعار SMS برفض الإرسالية
     */
    private function sendSmsRejectionNotification($statisticsProcessing, $user): void
    {
        try {
            if (!config('sms.notifications.sms', true)) {
                return;
            }

            $sendNotification = app(\App\Services\SendLoginNotificationService::class);
            $systemSmsSender = \App\Models\Setting::get_by_name('system_sms_sender') ?? 'DREAMS';

            $userLocale = $user->lang ?? config('app.locale', 'ar');
            $previousLocale = app()->getLocale();
            app()->setLocale($userLocale);

            $message = __('notification.sms.statistics.processing.user_rejected', [
                'processing_id' => $statisticsProcessing->processing_id
            ]);

            app()->setLocale($previousLocale);

            $sendNotification->sendSmsNotification(
                $systemSmsSender,
                $user->number,
                $message,
                'admin',
                $user->id
            );

            \Log::info("User rejection SMS notification sent", [
                'user_id' => $user->id,
                'processing_id' => $statisticsProcessing->processing_id,
                'user_locale' => $userLocale
            ]);

        } catch (\Exception $e) {
            \Log::warning("Failed to send SMS rejection notification", [
                'processing_id' => $statisticsProcessing->processing_id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}

