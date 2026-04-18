<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;
use App\Models\Payment;
use App\Models\Workspace;
use App\Models\Wallet;
use App\Models\ChargeRequestBank;
use App\Services\UrwayService;
use App\Services\FileUploadService;
use App\Class\payment\ServiceFactory;
use App\Class\payment\SmsService;
use App\Http\Responses\ValidatorErrorResponse;
use App\Traits\WalletManager;


class PaymentController extends BaseApiController
{
    use WalletManager;
    protected $urwayService;
    protected $fileUploadService;

    public function __construct(UrwayService $urwayService, FileUploadService $fileUploadService)
    {
        $this->urwayService = $urwayService;
        $this->fileUploadService = $fileUploadService;
    }

    // public static function middleware(): array
    // {
    //     return [
    //         new Middleware(
    //             'auth:api',
    //             except: ['urwayCallback']
    //         )
    //     ];
    // }


    public function index(Request $request)
    {
        $status = $request->query('status');
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        $query = Payment::whereNotNull('organization_id')
            ->with(['organization', 'paymentMethod', 'smsPlanTransaction', 'chargeRequestBank'])
            ->orderByDesc('id');
        if (!$request->user()->can('check.admin')) {
        }
        $payments = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $payments->getCollection()->map(function ($payment) {
            return new \App\Http\Responses\Payment($payment);
        });

        // Replace the collection with the transformed data
        $payments->setCollection($response);

        return $this->paginateResponse(true, 'Organizations retrieved successfully.', $payments);
    }

    public function organizationPayments(Request $request, Organization $organization)
    {

        $status = $request->query('status');
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        $query = Payment::with(['organization', 'paymentMethod', 'smsPlanTransaction', 'chargeRequestBank'])
            ->where('organization_id', $organization->id);

        if ($status) {
            $query->where('payment_status', $status);
        }

        $payments = $query->paginate($perPage, ['*'], 'page', $page);
        $response = $payments->getCollection()->map(function ($payment) {
            return new \App\Http\Responses\Payment($payment);
        });

        $payments->setCollection($response);
        return $this->paginateResponse(true, 'Organization payments retrieved successfully.', $payments);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/payments/{payment}/upload-invoice",
     *     summary="Upload invoice file for a payment",
     *     description="Upload an invoice file (PDF, image) for a specific payment record",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="invoice_file",
     *                     type="file",
     *                     description="Invoice file (PDF, JPEG, PNG, JPG, GIF)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Invoice file uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="errors"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     security={{"bearer": {}}}
     * )
     */

    public function uploadInvoiceFile(Request $request, Payment $payment)
    {
        $rules = [
            'invoice_file' => 'required|mimes:jpeg,png,jpg,gif,pdf|max:8048'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $path = $this->fileUploadService->upload($request->file('invoice_file'));
        $payment->invoice_file = $path;
        $payment->save();
        return $this->response(true, trans('message.ok'), null, 201);
    }


    /**
     * @OA\Post(
     *     path="/api/admin/payments/{payment}/process-status",
     *     summary="Approve or reject a payment",
     *     description="Admin endpoint to approve or reject a pending payment request",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="payment",
     *         in="path",
     *         description="Payment ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"ACCEPT", "REJECT"},
     *                 description="Payment approval status"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment status updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Payment not pending or unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payment not pending"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to change wallet balance"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     security={{"bearer": {}}}
     * )
     */
    public function processPaymentStatus(Request $request, Payment $payment)
    {
        $rules = [
            'status' => 'required|in:ACCEPT,REJECT'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        if ($payment->payment_status != Payment::STATUS_PENDING) {
            return $this->response(false, __('message.payment_not_pending'), null, 401);
        }
        if (!$payment->chargeRequestBank || $payment->chargeRequestBank->status != ChargeRequestBank::STATUS_PENDING) {
            return $this->response(false, __('message.payment_not_pending'), null, 401);
        }

        if ($request->status == "ACCEPT") {
            $payment->payment_status = Payment::STATUS_COMPLETED;
            $payment->save();
            $payment->chargeRequestBank->update([
                'status' => ChargeRequestBank::STATUS_APPROVED
            ]);
            $service = ServiceFactory::getService($payment->chargeRequestBank->service->id);
            if (!$service->ChangeWalletV2($payment->organization, $payment->amount, $payment->chargeRequestBank->points_cnt, ($payment->amount / $payment->chargeRequestBank->points_cnt))) {
                return $this->response(false, 'Failed to change wallet balance', null, 500);
            }
        } else {
            $payment->payment_status = Payment::STATUS_FAILED;
            $payment->save();

            $payment->chargeRequestBank->update([
                'status' => ChargeRequestBank::STATUS_REJECTED
            ]);
        }

        return $this->response(true, trans('message.ok'), null, 201);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/payments/{organization}/charge",
     *     summary="Charge an organization's wallet",
     *     tags={"Payments"},
     *     security={{ "apiAuth": {} }},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "service"},
     *             @OA\Property(
     *                 property="amount",
     *                 type="integer",
     *                 example=1000,
     *                 description="Amount to charge"
     *             ),
     *             @OA\Property(
     *                 property="service",
     *                 type="string",
     *                 enum={"sms", "other"},
     *                 example="sms",
     *                 description="Service type"
     *             ),
     *             @OA\Property(
     *                 property="sms_point",
     *                 type="integer",
     *                 example=100,
     *                 description="Required when service is 'sms'. Number of SMS points"
     *             ),
     *             @OA\Property(
     *                 property="reason",
     *                 type="string",
     *                 example="Monthly SMS package",
     *                 description="Optional reason for the charge"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Charge processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Charge processed successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/ValidatorErrorResponse"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error processing charge",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error processing charge: error details"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function organizationCharge(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
            'service' => 'required|in:sms,other',
            'sms_point' => 'required_if:service,sms|integer',
            'reason' => 'sometimes|string|max:255',
            'invoice_file' => 'sometimes|mimes:jpeg,png,jpg,gif,pdf|max:8048'
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        try {
            if ($request->invoice_file) {
                $path = $this->fileUploadService->upload($request->file('invoice_file'));
            } else {
                $path = null;
            }
            $data = [
                'slug' => (string) \Str::uuid(),
                'payment_method_id' => \App\Models\PaymentMethod::where('name', 'bank_transfer')->first()->id,
                'transaction_type' => 'wallet_charge',
                'payment_status' => "completed",
                'organization_id' => $organization->id,
                'transaction_id' => 0,
                'track_id' => 0,
                'response_code' => 0,
                'amount' => $request->amount,
                'currency' => 'SAR',
                'payment_type' => "Bank Transfer"
            ];
            $payment = Payment::create($data);
            $payment->chargeRequestBank()->create([
                'user_id' => \Auth::id(),
                'organization_id' => $organization->id,
                'points_cnt' => $request->sms_point,
                'amount' => $request->amount,
                'currency' => 'SAR',
                'receipt_attach' => $path,
                'status' => 1,
                'service_id' => \App\Models\Service::where('name', $request->service)->first()->id
            ]);

            $service = ServiceFactory::getService($request->service);
            $sms_point = $request->sms_point ?? 0;
            $sms_price = $request->amount > 0 ? ($sms_point / $request->amount) : 0;
            $service->ChangeWalletV2($organization, $request->amount, $request->sms_point ?? 0, $sms_price);
            return $this->response(true, 'Charge processed successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error processing charge: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/payments/workspaces/{workspace}/payment/checkout",
     *     summary="Process a checkout request for a service",
     *     description="This endpoint processes a checkout request, calculates the total amount including tax, determines SMS points if applicable, and generates a payment URL.",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"service", "amount"},
     *             @OA\Property(property="service", type="string", example="sms", description="The service type (sms or other)"),
     *             @OA\Property(property="amount", type="integer", example=100, description="The amount to be processed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment URL Generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment Url"),
     *             @OA\Property(property="data", type="string", example="https://payment-gateway.com?paymentid=1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="service", type="array", @OA\Items(type="string", example="The service field is required.")),
     *                 @OA\Property(property="amount", type="array", @OA\Items(type="string", example="The amount must be an integer."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Payment Online Disabled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payment online disabled"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred.")
     *         )
     *     )
     * )
     */


    public function processCheckoutRequest(Request $request, Organization $organization, Wallet $wallet) // Remove the extra {
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        $service_name = $wallet->service->name ?? 'other';
        $service = ServiceFactory::getService($service_name);
        $total_amount = $service->getTotalAmount($request->amount);

        $sms_point = 0;
        $sms_price = (string) $organization->getSmsPrice();
        if ($service instanceof SmsService) {
            $sms_point = $service->getOrganizationSmsPoints($organization, $request->amount);
        }
        $amount = (float) $request->amount;
        $this->urwayService->setAttributes([
            'trackid' => 'charge-' . $service_name . '-' . date('d') . rand(1, 100000),
            'amount' => $total_amount,
            'currency' => 'SAR',
            'merchantIp' => \Request::ip(),
            'udf3' => "{'sms_point':'$sms_point','sms_price':'$sms_price'}",
            'udf2' => route('payment.callback'),
            'udf1' => "{'service':'$service_name','net_amount':'$amount','walletID':'$wallet->id'}",
        ]);
        $result = $this->urwayService->createPayment();
        if (!empty($result->payid)) {
            $url = $result->targetUrl . '?paymentid=' . $result->payid;
            return $this->response(true, 'Payment Url', $url, 200);
        } else {
            return $this->response(false, 'Payment online disabled', null, 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/payment/callback  ",
     *     summary="Handle URWay payment callback",
     *     description="This endpoint handles the callback from URWay after a payment attempt.",
     *     operationId="urwayCallback",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="PaymentId", type="string", description="Payment ID"),
     *             @OA\Property(property="TrackId", type="string", description="Tracking ID"),
     *             @OA\Property(property="TranId", type="string", description="Transaction ID"),
     *             @OA\Property(property="Result", type="string", description="Payment result"),
     *             @OA\Property(property="ResponseCode", type="string", description="Response code from payment gateway"),
     *             @OA\Property(property="responseHash", type="string", description="Response hash for validation"),
     *             @OA\Property(property="cardBrand", type="string", description="Brand of the card used for payment"),
     *             @OA\Property(property="amount", type="number", description="Amount paid"),
     *             @OA\Property(property="maskedPAN", type="string", description="Masked PAN of the card"),
     *             @OA\Property(property="PaymentType", type="string", description="Type of payment (e.g., CreditCard)"),
     *             @OA\Property(property="UserField1", type="string", description="Additional user data, typically in JSON format"),
     *         ),
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment processed successfully"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized or failed payment"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */

    public function urwayCallback(Request $request)
    {
        $post = $request->all();
        $TrackId = $post['TrackId'];
        // $user = \Auth::user();
        $payment = Payment::where('payment_id', $post['PaymentId'])->first();
        if ($payment) {
            return $this->response(false, __('message.msg_error_proccess'), null, 401);
        }
        $is_success = $this->urwayService->isSuccess($post);
        $UserField1 = str_replace("'", '"', $post['UserField1']);
        $UserField3 = str_replace("'", '"', $post['UserField3']);
        $walletID = json_decode($UserField1)->walletID ?? 0;
        $points = json_decode($UserField3)->sms_point ?? 0;
        $service = json_decode($UserField1)->service ?? 'other';
        $service = ServiceFactory::getService($service);
        $sms_price = json_decode($UserField3)->sms_price ?? .12;
        $wallet = Wallet::where('id', $walletID)->first();
        $organizaionID = $wallet->wallettable instanceof Organization ? $wallet->wallettable->id : $wallet->wallettable->organization_id;
        $organizaion = Organization::where('id', $organizaionID)->first();
        $data = [
            'slug' => (string) \Str::uuid(),
            'payment_method_id' => \App\Models\PaymentMethod::where('name', 'visa')->first()->id,
            'wallet_id' => $wallet->id,
            'transaction_type' => 'wallet_charge',
            'payment_status' => $is_success ? "completed" : "failed",
            'response_message' => $post['Result'],
            'organization_id' => $organizaionID,
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
            'payment_type' => $post['PaymentType'] ?? 'CreditCard'
        ];
        $message = trans('urway.message_' . $data['response_code']);
        $payment = Payment::create($data);

        if ($is_success) {
            $amount = $service->getNetAmount($post['amount']);
            if (!$service->ChangeWalletV2($organizaion, $amount, $points, $sms_price)) {
                return $this->urwayRedirect('false', 'Transaction failed');
            }
            return $this->urwayRedirect('true', $message);
        } else {
            return $this->urwayRedirect('false', $message);
        }
    }
    /**
     * @OA\Get(
     *     path="/api/payment/workspaces/{workspace}/payment/total-amount",
     *     summary="Calculate the total amount including tax for a specific service",
     *     description="This endpoint calculates the total amount for a specified service (e.g., SMS or Other) including tax or other operations.",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"service", "amount"},
     *             @OA\Property(property="service", type="string", example="sms", description="The service type (sms or other)"),
     *             @OA\Property(property="amount", type="integer", example=100, description="The amount to be processed")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OK"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_amount", type="number", example=115)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error(s)"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="service", type="array", @OA\Items(type="string", example="The service field is required.")),
     *                 @OA\Property(property="amount", type="array", @OA\Items(type="string", example="The amount must be an integer."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="An error occurred.")
     *         )
     *     )
     * )
     */
    public function totalAmount(Request $request, Workspace $workspace)
    {
        $validator = Validator::make($request->all(), [
            'service' => 'sometimes|in:sms,other',
            'amount' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        $service = ServiceFactory::getService($request->service);

        $total_amount = $service->getTotalAmount($request->amount);
        return $this->response(true, trans('message.ok'), ['total_amount' => $total_amount], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/request-conversion",
     *     summary="Submit a request to convert points to currency or currency to points",
     *     description="Allows a user to submit a conversion request, which must be approved by an admin.",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "conversion_type"},
     *             @OA\Property(property="amount", type="number", format="float", example=100.00),
     *             @OA\Property(property="conversion_type", type="string", enum={"points_to_currency", "currency_to_points"}, example="points_to_currency")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Conversion request submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Conversion request submitted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation or processing error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error message explaining the problem"),
     *             @OA\Property(property="errors", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized")
     *         )
     *     ),
     *     security={
     *         {"bearerAuth": {}}
     *     }
     * )
     */
    public function requestConversion(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'conversion_type' => 'required|in:points_to_currency,currency_to_points'
        ]);
        $service = ServiceFactory::getService($request->conversion_type);
        try {
            $service->requestConversion(\Auth::user(), $request->amount);
        } catch (\Exception $e) {
            return $this->response(false, $e->getMessage(), null, 422);
        }
        return $this->response(true, 'Conversion request submitted successfully', null, 200);
    }


    private function urwayRedirect(string $status, string $message): \Illuminate\Http\RedirectResponse
    {
        $url = rtrim(env('URWAY_REDIRECT_URL'), '/') . '/payment';

        return redirect()->to($url . '?' . http_build_query([
            'type'    => 'payment',
            'status'  => $status,
            'message' => $message,
        ]));
    }
}
