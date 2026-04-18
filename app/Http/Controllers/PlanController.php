<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Organization;
use App\Models\Workspace;
use App\Models\OrganizationPlan;
use App\Models\Payment;
use App\Exports\PlanSheetExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PaymentPlan;
use App\Services\UrwayService;
use App\Class\payment\ServiceFactory;
use App\Enums\Service as EnumService;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use App\Models\ChargeRequestBank;
use App\Enums\Service as ServiceEnum;

class PlanController extends BaseApiController
{

    protected $urwayService;
    protected $fileUploadService;

    public function __construct(UrwayService $urwayService, FileUploadService $fileUploadService)
    {
        $this->urwayService = $urwayService;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/plans",
     *     summary="Get organization plans",
     *     description="Retrieve a paginated list of plans for an organization",
     *     tags={"Plans"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="items"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);
        $plans = $organization->plans()->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $response = $plans->getCollection()->map(function ($plan) {
            return new \App\Http\Responses\Plan($plan);
        });
        $plans->setCollection($response);

        return $this->paginateResponse(true, 'items', $plans);
    }

    /**
     * @OA\Get(
     *     path="/api/workspaces/{workspace}/plans/can-use",
     *     summary="Check workspace plan usage",
     *     description="Verify if workspace has SMS channel",
     *     tags={"Plans"},
     *     @OA\Parameter(
     *         name="workspace",
     *         in="path",
     *         required=true,
     *         description="Workspace ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Check successful"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Channel does not belong to workspace",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Channel does not belong to the specified workspace.")
     *         )
     *     )
     * )
     */
    public function checkPlanUsage(Request $request, Workspace $workspace)
    {
        if (!$workspace->channels()->where('channels.platform', "sms")->exists()) {
            return $this->response(false, 'Channel does not belong to the specified workspace.', null, 403);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}/plans/payment_method",
     *     summary="Get available payment methods",
     *     description="Retrieve list of payment methods",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="workspace",
     *         in="path",
     *         required=true,
     *         description="Workspace ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="paymentMethods"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string")
     *             ))
     *         )
     *     )
     * )
     */
    public function PaymentMethod(Organization $organization)
    {
        $paymentMethods = [
            ['id' => 1, 'name' => 'gateway'],
            ['id' => 2, 'name' => 'bank'],

        ];
        return $this->response(true, 'paymentMethods', $paymentMethods);
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/plans/gatway_checkout",
     *     summary="Process plan payment through gateway",
     *     description="Create a payment request for a plan through payment gateway",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="plan_id", type="integer", description="Plan ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment URL generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payment Url"),
     *             @OA\Property(property="data", type="string", example="https://payment-gateway.com/checkout")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Plan does not belong to organization"
     *     )
     * )
     */
    public function checkoutPlanGatway(Request $request, Organization $organization)
    {
        $rules = [
            'plan_id' => 'required|exists:organization_plan,id'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $organizationPlan = OrganizationPlan::where('id', $request->plan_id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$organizationPlan) {
            return $this->response(false, 'The plan does not belong to the specified organization.', null, 401);
        }
        $plan_id = $organizationPlan->id;
        $service = ServiceFactory::getService(ServiceEnum::SMS);
        $total_amount = $service->getTotalAmount($organizationPlan->price);

        // Set the attributes
        $this->urwayService->setAttributes([
            'trackid' => 'plan-' . $plan_id . '-' . date('d') . rand(1, 100000),
            'amount' => $total_amount,
            'currency' => 'SAR',
            'merchantIp' => \Request::ip(),
            'udf2' => route('plan.urway.callback', ['organization' => $organization->id]),
            'udf1' => "{'plan_id':'$plan_id'}",

        ]);

        // Create the payment
        $result = $this->urwayService->createPayment();

        // Check the response and take appropriate action

        if (!empty($result->payid)) {
            $url = $result->targetUrl . '?paymentid=' . $result->payid;
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


    /**
     * @OA\Get(
     *     path="/organizations/{organization}/plans/urway_callback",
     *     summary="Payment gateway callback",
     *     description="Handle the payment gateway callback after payment processing",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="PaymentId",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="UserField1",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirect to payment status page"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Payment validation failed"
     *     )
     * )
     */
    public function urwayCallback(Request $request, Organization $organization)
    {
        $post = $request->all();

        $PaymentId = $post['PaymentId'];
        $payment = Payment::where('payment_id', $PaymentId)->first();
        if ($payment) {
            return $this->response(false, __('message.msg_plan_pain'), null, 401);
        }
        $UserField1 = str_replace("'", '"', $post['UserField1']);
        $plan_id = json_decode($UserField1)->plan_id ?? null;
        $is_success = $this->urwayService->isSuccess($post);

        $plan = OrganizationPlan::where('id', $plan_id)->where('organization_id', $organization->id)->first();
        if (!$plan) {
            return $this->response(false, __('message.msg_error_plan_exist'), null, 401);
        }

        $data = [
            'slug' =>  (string) \Str::uuid(),
            'payment_method_id' => \App\Models\PaymentMethod::where('name', 'visa')->first()->id,
            'organization_id' => $organization->id,
            'payable_type' => OrganizationPlan::class,
            'transaction_type' => 'sms_plan',
            'payment_status' => $is_success ? "completed" : "failed",
            'payable_id' => $plan->id,
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


        $payment = Payment::create($data);




        $message = trans('urway_message_' . $data['response_code']);
        if ($is_success) {
            $smsPlanTransaction = $payment->smsPlanTransaction()->create([
                'plan_id' => $plan->id,
                'points_allocated' => $plan->points_cnt,
                'price_per_point' => $plan->price,
                'currency' => $plan->currency,
                'organization_id' =>  $organization->id
            ]);

            $service = ServiceFactory::getService(EnumService::SMS);
            $amount = $service->getNetAmount($post['amount']);
            if (!$service->ChangeWalletV2($organization, $amount, $plan->points_cnt, ($amount / $plan->points_cnt))) {

                return $this->urwayRedirect('false', 'Transaction failed');
            }
            return $this->urwayRedirect('true', $message);
        } else {
            return $this->urwayRedirect('false', $message);
        }

        // if ($is_success) {



        //     $admin_notify_announ = Announcement::where('name', 'buy_plan_via_online_payment_notify_admin')->first();
        //     if (in_array($admin_notify_announ->media, ['EMAIL', 'BOTH'])) {
        //         $admin_notify_announ = announcement::get_by_name('buy_plan_via_online_payment_notify_admin');
        //         $settings = Setting::get_by_name('receiver_email');
        //         $title = $admin_notify_announ->title_ar . " - " . $admin_notify_announ->title_en;
        //         $this->emailController->sendEmail($settings, $title, "Dreams SMS", $admin_notify_announ->text_email);
        //     }
        //     $this->sendSmsAdmin('Dreams', $user->number, $admin_notify_announ->text_sms);
        // } else {
        //     return $this->response(false, $message, null, 401);
        // }
    }

    /**
     * @OA\Post(
     *     path="/api/organizations/{organization}/plans/charge_request_bank",
     *     summary="Create bank charge request for plan",
     *     description="Submit a bank transfer request for purchasing a plan with receipt attachment",
     *     tags={"Payments"},
     *     @OA\Parameter(
     *         name="organization",
     *         in="path",
     *         required=true,
     *         description="Organization ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="plan_id", type="integer", description="Plan ID"),
     *             @OA\Property(property="receipt_attach", type="string", format="binary", description="Receipt attachment image")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Charge request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Plan payment submitted successfully"),
     *             @OA\Property(property="data", type="null")
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
     *     @OA\Response(
     *         response=401,
     *         description="Plan does not belong to organization",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The plan does not belong to the specified organization."),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function ChargeRequestBank(Request $request, Organization $organization)
    {
        $rules = [
            'plan_id' => 'required|exists:organization_plan,id',
            'receipt_attach' => 'required|mimes:jpeg,png,jpg,gif,pdf|max:8048'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }

        $organizationPlan = OrganizationPlan::where('id', $request->plan_id)
            ->where('organization_id', $organization->id)
            ->first();

        if (!$organizationPlan) {
            return $this->response(false, 'The plan does not belong to the specified organization.', null, 401);
        }
        $path = $this->fileUploadService->upload($request->file('receipt_attach'));
        if (empty($path)) {
            return $this->response(false, 'Failed to upload the receipt attachment', null, 0);
        }
        $data = [
            'slug' =>  (string) \Str::uuid(),
            'payment_method_id' => \App\Models\PaymentMethod::where('code', 'bank_transfer')->first()->id,
            'organization_id' => $organization->id,
            'payable_type' => OrganizationPlan::class,
            'transaction_type' => 'sms_plan',
            'payment_status' => "pending",
            'payable_id' => $organizationPlan->id,
            'transaction_id' => 0,
            "track_id" => 0,
            'response_code' => "0",
            'amount' => $organizationPlan->price,
            'currency' => 'SAR',
            'payment_type' => "Bank Transfer"
        ];
        $payment = Payment::create($data);
        $payment->chargeRequestBank()->create([
            'user_id' => \Auth::id(),
            'organization_id' => $organization->id,
            'points_cnt' => $organizationPlan->points_cnt,
            'amount' => $organizationPlan->price,
            'currency' => $organizationPlan->currency,
            'receipt_attach' => $path,
            'service_id' => \App\Models\Service::where('name', 'sms')->first()->id
        ]);
        $payment->smsPlanTransaction()->create([
            'plan_id' => $organizationPlan->id,
            'points_allocated' => $organizationPlan->points_cnt,
            'price_per_point' => $organizationPlan->price,
            'currency' => $organizationPlan->currency,
            'organization_id' =>  $organization->id
        ]);

        return $this->response(true, __('message.msg_plan_pain'), null, 201);
    }

    public function bankInfo(Organization $organization)
    {
        $data['bank_name'] = 'تحويل بنك الأهلي';
        $data['Account_No'] = '26483761000101';
        $data['iban'] = 'SA06 1000 0026 4837 6100 0101';
        return $this->response(true, 'bank info', $data);
    }


    public function exportPlan(Request $request, Organization $organization)
    {

        $query = $organization->plans()
            ->withPivot(['id', 'points_cnt', 'price', 'currency', 'is_custom', 'created_at'])
            ->get()
            ->map(fn($query) => new \App\Http\Responses\Plan($query));
        $totalRecords = $query->count();
        $fileName     = 'plan_export_' . time() . '.xlsx';
        return Excel::download(new PlanSheetExport($query), $fileName);
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
