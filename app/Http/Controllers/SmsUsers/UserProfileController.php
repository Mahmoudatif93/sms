<?php

namespace App\Http\Controllers\SmsUsers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use App\Repositories\UserProfileRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Responses\ValidatorErrorResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\UserOtp;
use App\Http\Controllers\Settings\EmailController;
use Illuminate\Support\Facades\Crypt;

class UserProfileController  extends BaseApiController implements HasMiddleware

{

    protected $emailController;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    protected $UserProfileRepository;
    public function __construct(UserProfileRepositoryInterface $UserProfileRepository, EmailController $emailController)
    {
        $this->UserProfileRepository = $UserProfileRepository;
        $this->emailController = $emailController;
    }
    public function viewUserProfile($user_id)
    {
        $UserProfile = $this->UserProfileRepository->findall($user_id);
        return $this->response(true, 'UserProfile', $UserProfile);

    }

    /**
     * @OA\Put(
     *     path="/api/SmsUsers/UserProfile",
     *     summary="Update user profile",
     *     tags={"User Profile"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="number", type="string", example="+1234567890")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="UserProfile"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="email exist before!"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     security={{"auth:api": {}}}
     * )
     */


    public function update(Request $request,$id)
    {
       $validator = Validator::make($request->all(), [
            'username' => 'string|min:5|max:50|unique:user',
            'name' => 'string|min:4|max:50',
            'email' => 'string|email|max:255|unique:user',
            'number' => 'numeric',
            'password' => 'string|min:8|confirmed',
            'country_id' => 'exists:country,id',
            'phone' => 'string',
            'address' => 'string',

        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);

        }
        $email =  $this->UserProfileRepository->check_email_availability($request->email);
        if (!empty($email)) {
            return $this->response(false,  'email exist before!', null, 400);

        }
        $number =  $this->UserProfileRepository->check_number_availability($request->number);

        if (!empty($number)) {
            return $this->response(false,__('message.msg_error_number_exist') , null, 400);

        }

        $data = $request->all();
        $data['activation_code'] = rand(10000, 99999);
        $user_id = Auth::id();  // Update with the authenticated user ID
        $user = Auth::user();
        $post = $this->UserProfileRepository->update($user_id, $data);
        $messagesender = '';
        $this->emailController->sendEmail($user->email,  __('message.msg_update_failed'), "Dreams SMS", $messagesender);
        //return $this->response(true, 'تم عمل تعديل في بيانات الحساب ');
        return $this->response(true, 'UserProfile', $post);


        //return response()->json($post);
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/refrsh_key",
     *     summary="Refresh user's secret key",
     *     description="Generates a new secret key for the authenticated user",
     *     tags={"User Profile"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="data"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="secret_key", type="string", example="1234567890abcdef1234567890abcdef")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     security={{"auth:api": {}}}
     * )
     */

    public function refrshKey()
    {
        $key = bin2hex(random_bytes(32));
        $data['secret_key'] = Crypt::encrypt($key);
        $user_id = Auth::id();  // Update with the authenticated user ID
        $this->UserProfileRepository->refrsh_key($user_id, $data);
        return $this->response(true, 'data', ['secret_key'=>$key]);

    }


    /**
     * @OA\Post(
     *     path="/api/SmsUsers/notification_save",
     *     summary="Save user notification settings",
     *     description="Saves the notification settings for the authenticated user",
     *     tags={"User Profile"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"notification_number", "notification_limit", "notification_status"},
     *             @OA\Property(property="notification_number", type="string", maxLength=32, example="123456"),
     *             @OA\Property(property="notification_limit", type="integer", minimum=0, maximum=9999999999, example=100),
     *             @OA\Property(property="notification_status", type="integer", enum={0, 1}, example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="تم عمل تعديل في بيانات الحساب")
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
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated.")
     *         )
     *     ),
     *     security={{"auth:api": {}}}
     * )
     */
    public function notificationSave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_number' => 'required|string|max:32',
            'notification_limit' => 'required|numeric|digits_between:0,10',
            'notification_status' => 'required|numeric|digits_between:0,1',

        ]);
        if ($validator->fails()) {
            return $this->response(false, 'errors', $validator->errors(), 400);
        }
        $notification_number = $request->notification_number;
        $notification_limit = $request->notification_limit;
        $notification_status = $request->notification_status;
        $data['notification_number']  = $notification_number;
        $data['notification_status']  = (empty($notification_status) ? "0" : "1");
        $data['notification_limit']  = $notification_limit;
        $data['notification_has_sent']  = 0;
        $user_id = Auth::id();  // Update with the authenticated user ID
        $post = $this->UserProfileRepository->notification_save($user_id, $data);
        return $this->response(true,  __('message.msg_update_failed'));

        //return response()->json($post);
    }
}
