<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Http\Responses\Login;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Otp;
use App\Models\Supervisor;
use App\Services\Admin\AdminLoginService;
use App\Services\SendLoginNotificationService;
use App\Services\Sms;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends SmsApiController implements HasMiddleware
{
    protected $sms;
    protected $UserLoginService;
    protected $SendLoginNotification;
    protected $UserRegisterService;

    public function __construct(SMS $sms, AdminLoginService $UserLoginService, SendLoginNotificationService $SendLoginNotification)
    {
        parent::__construct($sms);
        $this->UserLoginService = $UserLoginService;
        $this->SendLoginNotification = $SendLoginNotification;

    }

    public static function middleware(): array
    {
        return [
            new Middleware(
                'check.admin',
                except: ['login', 'recaptch','ResendOtp']
            ),
        ];
    }

    public function login(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:supervisor,username',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'errors', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }
        ////recaptcha validation
        $this->UserLoginService->recaptchaVerify($request);

        $username = $request->get('username');
        $password = $request->get('password');
        $user_data = Supervisor::getByUsername($username);

        if (empty($user_data)) {
            Log::error([$username, __('message.msg_error_login')]);
            return $this->response(false, __('message.msg_error_login'), null, 401);
        }

        if (!Hash::check($password, $user_data->password)) {
            return $this->UserLoginService->handleFailedLogin($user_data, $request);
        }
        if ($user_data->status == 0) {
            return $this->UserLoginService->restrictedUser();
        }

        return $this->UserLoginService->handleSuccessfulLogin($user_data, $username, $password, $request);
    }

    public function logout(Request $request)
    {

        Otp::where(array('user_id' => Auth::id()))->delete();
        $request->user()->update(['otp' => null]);
        Auth::guard('admin')->logout();
        return $this->response(true, __('message.msg_logged_successfully'), null, 201);
    }

    public function verifyotpcode(Request $request): JsonResponse
    {

        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|string|email|max:255|exists:supervisor',
                'otp' => 'required|string|',
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }

        $user = Supervisor::whereEmail($request->email)->first();

        $otpRecord = Otp::getOtpRecord($request->otp, $user->id,1);

        if ($user->otp != $request->otp) {
            return $this->response(false, __('message.msg_error_invalid_activation'), null, 400);
        }
        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            Otp::where(array('otp' => $request->otp, 'user_id' => $user->id))->delete();
            return $this->response(false, __('message.msg_error_expired_activation'), null, 400);
        }

        Otp::where(array('otp' => $request->otp, 'user_id' => $user->id))->delete();
        $user->update(['otp' => null]);
        return $this->response(true, __('message.msg_Active_successfully'), $user, 201);
    }

    public function ResendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|string|email|max:255|exists:supervisor',
            ]
        );

        $user = supervisor::where('email', $request->email)
            ->first();
        $otp = rand(10000, 99999);

        // update activation_code to  user account
        $user->update(['otp' => $otp]);
        $this->otp($otp, $user->id);
        $this->UserLoginService->sendNotifications($user, $otp,$request);
        return $this->response(true, __('message.msg_send_successfully'), $user, 201);

    }

    public function otp($otp, $user_id)
    {
        $expiresAt = Carbon::now()->addMinutes(2); // Set expiry time to 2 minutes from now

        Otp::create([
            'user_id' => $user_id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'type' => 1,
        ]);

        $otpRecord = Otp::getOtpRecord($otp, $user_id,1);
        if (!$otpRecord) {
            return $this->response(false, __('message.msg_error_invalid_otp'), null, 400);
        }
        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            return $this->response(false, __('message.msg_error_otp_expired'), null, 400);
        }
        return $this->response(true, __('message.msg_valid_otp'), null, 201);
    }

    public function me(Request $request)
    {
        $authenticatedUser = auth('admin')->user();
        $response = Supervisor::find($authenticatedUser->getAuthIdentifier());
        return $this->response(true, __('message.msg_send_successfully'), [
            'supervisor' => $response,
        ]);

    }
}
