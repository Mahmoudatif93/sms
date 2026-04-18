<?php

namespace App\Http\Controllers\SmsUsers;

use Illuminate\Http\Request;
use App\Http\Controllers\SmsApiController;
use App\Repositories\SenderRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Models\Sender;
use App\Models\User;
use App\Models\Payments;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use App\Models\ChargeRequestBank;
use App\Models\Paymentsender;
use App\Models\Setting;
use App\Http\Controllers\Settings\EmailController;
use App\Services\UrwayService;
use App\Services\AuditLogService;
use App\Services\Sms;
use App\Services\FileUploadService;
use App\Models\Wallet;
use App\Models\Service;
use App\Enums\Service as EnumService;
use App\Models\BalanceUser;
use App\Services\SendLoginNotificationService;

class SendersController extends SmsApiController implements HasMiddleware
{


    public static function middleware(): array
    {

        return [
            new Middleware(
                'auth:api',
                except: ['urwayCallback']
            )
        ];
    }
    protected $fileUploadService;
    protected $SenderRepository;
    protected $SendLoginNotification;
    protected $urwayService;

    public function __construct(
        FileUploadService $fileUploadService,
        SenderRepositoryInterface $SenderRepository,
        SendLoginNotificationService $SendLoginNotification,
        UrwayService $urwayService,
        SMS $sms
    ) {
        parent::__construct($sms);
        $this->SenderRepository = $SenderRepository;
        $this->SendLoginNotification = $SendLoginNotification;

        $this->urwayService = $urwayService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/senders/",
     *     summary="Get list of senders",
     *     description="Retrieve a list of senders with optional pagination and search",
     *     operationId="indexSenders",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Get all senders without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering senders",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

    public function index(Request $request)
    {
        if (isset($request->all) && $request->all == true) {

            if (isset($request->search)) {
                $search = $request->search;
                $items = Sender::where('user_id', Auth::id())
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('side_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_email', 'like', '%' . $search . '%')
                            ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                    })
                    ->orderBy('created_at', 'DESC')->get();
            } else {
                $items = Sender::where('user_id', Auth::id())->orderBy('created_at', 'DESC')->get();
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
                $items = Sender::where('user_id', Auth::id())
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('side_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_email', 'like', '%' . $search . '%')
                            ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                    })
                    ->orderBy('created_at', 'DESC')->paginate($perPage);
            } else {
                $items = Sender::where('user_id', Auth::id())->orderBy('created_at', 'DESC')->paginate($perPage);
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


       /**
     * @OA\Get(
     *     path="/api/SmsUsers/senders/{id}",
     *     summary="Get sender details",
     *     description="Retrieve details of a specific sender",
     *     operationId="showSender",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to retrieve",
     *         required=true,
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
     *             @OA\Property(property="message", type="string", example="senders"),
     *             @OA\Property(property="data", type="object"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sender not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sender not found"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

     public function show($id)
     {

         $senders = $this->SenderRepository->findbyid($id);

         return $this->response(true, 'senders', $senders);
     }


    /**
     * @OA\Post(
     *     path="/api/SmsUsers/senders",
     *     summary="Create a new sender",
     *     tags={"Senders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "file_authorization_letter", "file_other"},
     *             @OA\Property(property="name", type="string", maxLength=14, example="Sender Name"),
     *             @OA\Property(property="file_authorization_letter", type="string", format="binary"),
     *             @OA\Property(property="file_commercial_register", type="string", format="binary"),
     *             @OA\Property(property="file_value_added_tax_certificate", type="string", format="binary"),
     *             @OA\Property(property="file_other", type="string", format="binary"),
     *             @OA\Property(property="note", type="string", maxLength=255),
     *             @OA\Property(property="side_type", type="integer", enum={1, 2, 3}),
     *             @OA\Property(property="side_name", type="string"),
     *             @OA\Property(property="type", type="string"),
     *             @OA\Property(property="commercial_register", type="string"),
     *             @OA\Property(property="delegate_name", type="string"),
     *             @OA\Property(property="delegate_email", type="string"),
     *             @OA\Property(property="max_sms_one_day", type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Sender created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sender created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function store(Request $request)
    {
        //required|
        $rules = [
            'name' => 'required|string|max:14',
            'file_authorization_letter' => 'required|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_commercial_register' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_value_added_tax_certificate' => 'file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'file_other' => 'nullable|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120',
            'note' => 'nullable|string|max:255',
        ];

        // Adjust rules based on side_type
        $sender_same_name_and_accepted = false;
        if ($request->side_type == 2) {
            $rules['file_commercial_register'] = 'required|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120';
        } elseif ($request->side_type == 3) {
            $rules['file_commercial_register'] = 'required|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120';
            $rules['file_value_added_tax_certificate'] = 'required|file|mimes:csv,pdf,doc,docx,jpeg,png,jpg,gif|max:10120';
        }
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $data = $request->all();

        $data['file_authorization_letter'] = $this->fileUploadService->upload($request->file_authorization_letter);
        $data['file_other'] = $this->fileUploadService->upload($request->file_other);


        if ($request->side_type == 2) {
            $data['file_commercial_register'] = $this->fileUploadService->upload($request->file_commercial_register);
        } elseif ($request->side_type == 3) {
            $data['file_commercial_register'] = $this->fileUploadService->upload($request->file_commercial_register);
            $data['file_value_added_tax_certificate'] = $this->fileUploadService->upload($request->file_value_added_tax_certificate);
        }

        $data['user_id'] = Auth::id();  // Update with the authenticated user ID
        $data['name'] = $request->name;
        $data['side_name'] = $request->side_name;
        $data['side_type'] = $request->side_type;
        $data['type'] = $request->type;
        $data['commercial_register'] = $request->commercial_register;
        $data['delegate_name'] = $request->delegate_name;
        $data['delegate_email'] = $request->delegate_email;
        $data['max_sms_one_day'] = $request->max_sms_one_day;
        $data['note'] = $request->note;

        $sender = $this->SenderRepository->create($data);
        $user_id = Auth::id();
        $user = auth('api')->user();
        $event_description = "User #{$user_id} have requested sender name {$request->name} ";
        Sender::logEventAudit('RequestSenderName', $event_description, 'Sender', $sender->id, $data, $user_id, 'User');

        $this->SenderNotify($user, $sender, $sender_same_name_and_accepted);
        return $this->response(true, 'senders', $sender);
    }
    /**
     * @OA\Put(
     *     path="/api/SmsUsers/senders/{id}",
     *     summary="Update a sender",
     *     description="Update an existing sender's information",
     *     operationId="updateSender",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to update",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=14),
     *             @OA\Property(property="file_authorization_letter", type="string", format="binary"),
     *             @OA\Property(property="file_commercial_register", type="string", format="binary"),
     *             @OA\Property(property="file_value_added_tax_certificate", type="string", format="binary"),
     *             @OA\Property(property="file_other", type="string", format="binary"),
     *             @OA\Property(property="note", type="string", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="senders"),
     *             @OA\Property(property="data", type="object")
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
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sender not found"
     *     )
     * )
     */

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

        $senderData = Sender::where(array( 'id' => $id))->first();
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
        $data['user_id'] =$senderData->user_id;  // Update with the authenticated user ID
        $sender = $this->SenderRepository->update($id, $data);
        $sender_same_name_and_accepted = ($sender->name == $request->name && $sender->status == 1);
        $user_id = $senderData->user_id;
        $user =User::find($user_id);

        $event_description = "User #{$user_id} have update sender name {$request->name} ";
        Sender::logEventAudit('RequestSenderName', $event_description, 'Sender', $sender->id, $data, $user_id, 'User');

        $this->SenderNotify($user, $sender, $sender_same_name_and_accepted);
        return $this->response(true, 'senders', $sender);
    }
    /**
     * @OA\Delete(
     *     path="/api/SmsUsers/senders/{id}",
     *     summary="Delete a sender",
     *     description="Delete a specific sender for the authenticated user",
     *     operationId="destroySender",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to delete",
     *         required=true,
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
     *             @OA\Property(property="message", type="string", example="Sender deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Sender not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sender not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

    public function destroy($id)
    {

        $Senders = Sender::where(array('user_id' => Auth::id(), 'id' => $id))->first();
        if ($Senders != null) {

            $this->SenderRepository->delete($id);
            return $this->response(true, __('message.msg_delete_row'));
        } else {
            return $this->response(false, 'errors', __('message.lbl_sender_not_found'), 400);
        }
    }


    /**
     * @OA\Delete(
     *     path="/api/SmsUsers/deleteSelectedsenders",
     *     summary="Delete multiple senders",
     *     description="Delete multiple senders for the authenticated user",
     *     operationId="deleteSelectedSenders",
     *     tags={"Senders"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(property="ids", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Senders deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

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
    /**
     * @OA\Get(
     *     path="/api/SmsUsers/updateDefault/{id}",
     *     summary="Update default sender",
     *     description="Set a sender as default and unset others",
     *     operationId="updateDefaultSender",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to set as default",
     *         required=true,
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
     *             @OA\Property(property="message", type="string", example="Sender updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Update failed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sender not found"
     *     )
     * )
     */


    public function updateDefault($id)
    {
        $updated = Sender::updateByArray(['default' => 0], ['user_id' => Auth::id()]);
        // Update the user's 'default' attribute to 1 based on their ID
        $updated = Sender::updateByArray(['default' => 1], ['id' => $id]);
        if ($updated) {
            return $this->response(true, __('message.msg_update_row'));
        } else {
            return $this->response(false, __('message.msg_update_failed'));
        }
    }

    /**
     * @OA\Post(
     *     path="/api/SmsUsers/buy_sender_bank/{id}",
     *     summary="Buy sender using bank transfer",
     *     description="Process the purchase of a sender using bank transfer",
     *     operationId="buySenderBank",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to purchase",
     *         required=true,
     *         @OA\Schema(
     *             type="integer",
     *             format="int64"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="receipt_attach",
     *                     type="file",
     *                     description="Receipt attachment (jpg, jpeg, png, pdf, max 2MB)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="payment_bank_id", type="integer"),
     *                 @OA\Property(property="user_id", type="integer"),
     *                 @OA\Property(property="sender_id", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Payment not available",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payment for this sender is not available")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */

    public function buy_sender_bank($id, Request $request)
    {
        $user_id = Auth::id();
        $user = User::find($user_id);
        $entry = Sender::find($id);
        if ($entry->status == "payment_watting") {

            $validator = Validator::make($request->all(), [
                'receipt_attach' => 'required|file|mimes:jpg,jpeg,png,pdf|max:2048', // 2MB max
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 422); // Return validation errors
            }

            $receipt_attach =  $this->fileUploadService->upload($request->file('receipt_attach'));  //$this->bank_upload_receipt($request);
            // if ($receipt_attach['success']) {
            $paymentMethod = new ChargeRequestBank;
            $paymentMethod->user_id = Auth::id();
            $paymentMethod->points_cnt = 0;
            $paymentMethod->amount = 230;
            $paymentMethod->currency = 'SAR';
            $paymentMethod->receipt_attach = $receipt_attach;
            $paymentMethod->type = 2;
            $paymentMethod->request_date = now();
            $paymentMethod->save();
            $payment_id = $paymentMethod->id;
            $data = [
                'payment_bank_id' => $payment_id,
                'user_id' => Auth::id(),
                'sender_id' => $id,
            ];
            Paymentsender::create($data);
            $entry->payment();

            $event_description = "User #{$user_id} have accepted sender name {$entry->name} via Bank receipt";
            $changes = ['field_name' => 'status', 'old_value' => 'pending', 'new_value' => 'Successful'];
            $entity_type = 'Bank_receipt ';
            Sender::logEventAudit('AcceptSenderName', $event_description, $entity_type, $payment_id, $changes, $user_id, 'User');
            $this->send_notfication($user->number);

            return $this->response(true, __('message.msg_paid_success'), [
                'data' => $data
            ], 201);
        } else {
            return $this->response(false, 'errors', __('message.msg_payment_sender_not_available'), 401);
        }
    }


    public function bank_upload_receipt($request)
    {
        $validator = Validator::make($request->all(), [
            'receipt_attach' => 'required|image|mimes:jpeg,png,jpg,gif|max:8048'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $data = $request->all();
        return $this->handleFileUploads($data);
    }

    protected function handleFileUploads(array $data, $model = null)
    {
        $userId = auth()->id();
        foreach ($data as $key => $value) {
            if (is_file($value)) {
                $folderName = 'uploads/receipt_attach/' . $userId;
                $fileName = $value->getClientOriginalName();
                $filePath = $value->storeAs($folderName, $fileName, 'public');
                $data[$key] = Storage::url($filePath);
            }
        }

        return $this->response(true, 'File processed', $data, 200);
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/buySenderWallet/{id}",
     *     summary="Buy sender using wallet balance",
     *     description="Purchase a sender using the user's wallet balance",
     *     operationId="buySenderWallet",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to purchase",
     *         required=true,
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
     *             @OA\Property(property="message", type="string", example="Sender purchased successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient wallet balance or wallet not found"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sender not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sender not found"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */

    public function buySenderWallet($id)
    {
        $sender = Sender::findOrFail($id);
        //TODO: balance from where
        $serviceId = Service::where('name', EnumService::OTHER)->value('id');
        $wallet = Wallet::where(['user_id' => Auth::id(), 'service_id' => $serviceId, 'status' => 'active'])->first();

        if (!$wallet) {
            return $this->response(false, trans('message.msg_error_no_wallet_found'), [], 400);
        }
        //TODO: get 200 from cota
        if ($wallet->amount >= 200) {
            $sender_name = $sender->name;
            $charge = auth()->user()->addBalanceCurrency(-200, "Activate sender name ($sender_name)");

            if ($charge) {
                //TODO: atif: Complete the process
                $user_id = Auth::id();
                $user = auth('api')->user();
                $sender->payment();
                $event_description = "User #{$user_id} have accepted sender name {$sender->name} via Wallet";
                $changes = ['field_name' => 'status', 'old_value' => 'pending', 'new_value' => 'Successful'];
                $entity_type = 'Wallet';
                $paymentsender = new Paymentsender([
                    'user_id' => $user_id,
                    'sender_id' => $sender->id,
                ]);
                // Associate the wallet with the paymentsender
                $paymentsender->walletable()->associate($wallet);
                $paymentsender->save();
                Sender::logEventAudit('AcceptSenderName', $event_description, $entity_type, $wallet->id, $changes, $user_id, 'User');
                $this->send_notfication($user->number);
            }
        }
    }


    //////////////////////////////////buy_senders_gateway////////////////////////////////////////////////
    /**
     * @OA\Get(
     *     path="/api/SmsUsers/buy_senders_gateway/{id}",
     *     summary="Initiate payment for a sender via payment gateway",
     *     description="Creates a payment request for a sender using Urway payment gateway",
     *     operationId="buySendersGateway",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the sender to purchase",
     *         required=true,
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
     *             @OA\Property(property="message", type="string", example="Payment Url"),
     *             @OA\Property(property="data", type="string", example="https://payments.urway-tech.com/URWAYPGService/direct.jsp?paymentid=123456789")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Payment failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Transaction failed due to insufficient funds"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Sender not found"
     *     )
     * )
     */

    public function buy_senders_gateway($id, Request $request)
    {
        // Set the attributes
        $this->urwayService->setAttributes([
            'trackid' => 'sender-' . $id . '-' . date('d') . rand(1, 100000),
            'amount' => 230,
            'currency' => 'SAR',
            'merchantIp' => \Request::ip(),
            'udf2' => route('Senders_urway_callback')
        ]);

        // Create the payment
        $result = $this->urwayService->createPayment();

        // Check the response and take appropriate action
        if (!empty($result->payid)) {
            $url = $result->targetUrl . '?paymentid=' . $result->payid;
            //return Redirect::to($url);
            return $this->response(true, 'Payment Url', $url, 200);
        } else {
            // Payment failed
            $message_error = __('لا يمكن اتمام');
            if (!empty($result->responseCode)) {
                if ($result->responseCode == 601) {
                    $error = 'Transaction failed due to insufficient funds';
                } else {
                    $error = $result->responseCode;
                }

                $message_error = __('urway_message_' . $error);
            }
            return $this->response(false, $message_error, null, 401);
        }
    }

    // Define the callback method to handle the response from Urway
    public function urwayCallback(Request $request)
    {
        $post = $request->all();
        $sender_id = count(explode('-', $post['TrackId'])) > 0 ? explode('-', $post['TrackId'])[1] : 0;
        $entry = Sender::find($sender_id);
        $user_id = $entry->user_id;
        $user = User::find($user_id);
        $payment = Payments::where('payment_id', $post['PaymentId'])->first();
        if ($payment) {
            return $this->response(false, __('message.msg_paid_before'), null, 401);
        }

        $invoice_file = "";

        if ($entry) {
            $invoice_file = $entry->invoice_file;
        }

        $is_success = $this->urwayService->isSuccess($post);
        $data = [
            'user_id' =>  $user_id,
            'payment_id' => $post['PaymentId'],
            'transaction_id' => $post['TranId'],
            'status' => $post['Result'],
            'track_id' => $post['TrackId'],
            'response_code' => $post['ResponseCode'],
            'response_hash' => $post['responseHash'],
            'card_brand' => $post['cardBrand'],
            'amount' => $post['amount'],
            'currency' => 'SAR',
            'masked_pan' => $post['maskedPAN'],
            'payment_type' => $post['PaymentType'],
            'invoice_file' => $invoice_file,
            'type' => 2,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $message = trans('urway_message_' . $data['response_code']);

        $payment_id = Payments::insert($data);

        if ($is_success) {
            $entry->payment();
            $event_description = "User #{$user_id} have accepted sender name {$entry->name} via payment online";
            $changes = ['field_name' => 'status', 'old_value' => 'pending', 'new_value' => 'Successful'];

            $entity_type = 'Payment';
            Sender::logEventAudit('AcceptSenderName', $event_description, $entity_type, $payment_id, $changes, $user_id, 'User');


            $this->send_notfication($user->number);
        } else {
            return $this->response(false, $message, null, 401);
        }
        return redirect('https://portal.dreams.sa');
    }
    /**
     * @OA\Get(
     *     path="/api/SmsUsers/user_sender",
     *     summary="Get user senders",
     *     description="Retrieve active senders for a user, including granted senders",
     *     operationId="getUserSenders",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Get all senders without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="ID of the user to get senders for",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering senders",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="items"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="items", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="selected", type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="id", type="integer"),
     *                         @OA\Property(property="name", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */


    public function user_sender(Request $request)
    {

        if (isset($request->all) && $request->all == true) {
            $id = $request->user_id;
            $parent_id = User::find($id)->parent_id;

            if (isset($request->search)) {
                $items = Sender::get_active_by_user_id($parent_id, $request->search);
            } else {
                $items = Sender::get_active_by_user_id($parent_id);
            }

            $granted_sender_ids = User::find($id)->granted_sender_ids;
            $senderid = explode(',', $granted_sender_ids);

            $granted_senders = Sender::whereIn('id', $senderid)->get();

            // Prepare an array to store the granted group details
            $grantedSendersWithNames = [];

            // Loop through each granted group and extract the ID and name
            foreach ($granted_senders as $sender) {
                $grantedSendersWithNames[] = [
                    'id' => $sender->id,
                    'name' => $sender->name
                ];
            }

            return response()->json([
                "success" => true,
                "message" => "items",
                "data" => [
                    "items" => $items,
                    "selected" => $grantedSendersWithNames
                ]
            ]);
        } else {

            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            // Fetch paginated dataa
            if ($request->search != null) {
                $search = $request->search;
                $items = Sender::where('user_id', $request->user_id)
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', '%' . $search . '%')
                            ->orWhere('side_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_name', 'like', '%' . $search . '%')
                            ->orWhere('delegate_email', 'like', '%' . $search . '%')
                            ->orWhere('delegate_mobile', 'like', '%' . $search . '%');
                    })
                    ->orderBy('created_at', 'DESC')->paginate($perPage);
            } else {
                $items = Sender::where('user_id', $request->user_id)->orderBy('created_at', 'DESC')->paginate($perPage);
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

    public function send_notfication($number)
    {

        $admin_notify_announ = Setting::get_by_name('admin_sender_request');
        $settings = Setting::get_by_name('receiver_email');
        $this->SendLoginNotification->sendEmailNotification($settings, 'طلب اسم مرسل', 'Dreams SMS', 'تمت عملية الدفع');
        $this->sendSmsAdmin('Dreams', $number, __('message.msg_paid_success'));
    }



    /**
     * @OA\Get(
     *     path="/api/SmsUsers/getSmsSenders",
     *     summary="Get SMS senders for the authenticated user",
     *     description="Retrieves active senders, all senders, or a default sender for the authenticated user",
     *     operationId="getSmsSenders",
     *     tags={"Senders"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="items"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="user_id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="SenderName"),
     *                     @OA\Property(property="side_name", type="string", nullable=true),
     *                     @OA\Property(property="side_type", type="string", nullable=true),
     *                     @OA\Property(property="type", type="string", nullable=true),
     *                     @OA\Property(property="commercial_register", type="string", nullable=true),
     *                     @OA\Property(property="unified_number", type="string", nullable=true),
     *                     @OA\Property(property="is_sent_to_hawsabah", type="boolean", nullable=true),
     *                     @OA\Property(property="status", type="integer", example=1),
     *                     @OA\Property(property="default", type="string", example="static"),
     *                     @OA\Property(property="date", type="string", format="date-time", nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */


    public function getSmsSenders(Request $request)
    {
        $userId = Auth::id();

        // Get active senders and all senders
        $activeSenders = Sender::get_active_by_user_id($userId);
        $allSenders = Sender::where('user_id', $userId)->get();

        // Determine what to return
        if ($activeSenders->isNotEmpty()) {
            $items = $activeSenders;
        } elseif ($allSenders->isNotEmpty()) {
            $items = null;
        } else {
            // Default "Dreams" sender when no active or inactive senders are found
            $items = collect([[
                'id' => 0,
                'user_id' => $userId,
                'name' => 'Dreams',
                'side_name' => null,
                'side_type' => null,
                'type' => null,
                'commercial_register' => null,
                'unified_number' => null,
                'is_sent_to_hawsabah' => null,
                'status' => 1,
                'default' => 'static',
                'date' => null,
                'created_at' => now(),
            ]]);
        }

        // Return the response with items
        return $this->response(true, 'items', $items);
    }
    public  function SenderNotify($user, $sender, $sender_same_name_and_accepted)
    {
        $admin_sender_request = Setting::get_by_name('admin_sender_request');
        $receiver_email = Setting::get_by_name('receiver_email');
        $Emailsubject = __('message.lbl_sender_request');
        $system_sms_sender = Setting::get_by_name('system_sms_sender');
        $receiver_number = Setting::get_by_name('receiver_number');
        $Loginmessage = __('message.msg_info_messages_sender_request');
        $array_params =   array(
            '{username}' => $user->username
        );
        if (!empty($array_params)) {
            $param_names = array_keys($array_params);
            $param_values = array_values($array_params);
            $Loginmessage = str_replace($param_names, $param_values, $Loginmessage);
        }

        if ($user->unlimited_senders || $sender_same_name_and_accepted) {

            $sender->enable();
        } else {
            if ($admin_sender_request == "EMAIL" ||  $admin_sender_request == "BOTH") {
                $this->SendLoginNotification->sendEmailNotification($receiver_email, $Emailsubject, 'Dreams SMS', $Loginmessage);
            }

            if ($admin_sender_request == "SMS" ||  $admin_sender_request == "BOTH") {
                $this->SendLoginNotification->sendSmsNotification($system_sms_sender, $receiver_number, $Loginmessage, 'admin');
            }
        }
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/paymentsender/{id}/wallet",
     *     summary="Get wallet associated with a payment sender",
     *     tags={"Senders"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the payment sender",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="wallet", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Wallet not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="errors", type="string", example="No wallet associated with this payment sender.")
     *         )
     *     )
     * )
     */

    public function getWalletSenders($id)
    {
        // Find the Paymentsender by ID
        $paymentsender = Paymentsender::find($id);

        // Check if Paymentsender exists
        if (!$paymentsender) {
            return $this->response(false, 'errors', 'No wallet associated with this payment sender.', 404);
        }

        // Get the associated Wallet using the polymorphic relationship
        $wallet = $paymentsender->walletable;

        // Check if Wallet exists
        if (!$wallet) {
            return $this->response(false, 'errors', 'No wallet associated with this payment sender.', 404);
        }

        // Return the Wallet data
        return $this->response(true, 'wallet', $wallet);
    }
}
