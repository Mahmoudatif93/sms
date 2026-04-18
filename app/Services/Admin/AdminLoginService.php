<?php

namespace App\Services\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\AdminFailedLogin;
use App\Models\announcement;
use App\Models\Otp;
use App\Models\Setting;
use App\Services\RecaptchaService;
use App\Services\SendLoginNotificationService;
use App\Services\Sms;
use Carbon\Carbon;
use App\Http\Responses\AdminLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminLoginService extends SmsApiController
{
    protected $sms;
    protected $SendLoginNotification;
    protected $recaptcha;

    public function __construct(Sms $sms, SendLoginNotificationService $SendLoginNotification, RecaptchaService $recaptcha)
    {
        $this->sms = $sms;
        $this->SendLoginNotification = $SendLoginNotification;
        $this->recaptcha = $recaptcha;
    }

    public function handleSuccessfulLogin($supervisor, $username, $password, $request)
    {

        if (!$token = auth('admin')->attempt(compact('username', 'password'))) {
            return $this->response(false, __('message.msg_error_Unauthorized'), null, 401);
        }
        Log::info([$username, __('messages.msg_success_login'), 'timestamp' => date('Y-m-d H:i:s')]);
        $otp = rand(10000, 99999);

        $this->prepareUserData($supervisor, $otp);

        $this->sendNotifications($supervisor, $otp, $request);

        $response = new AdminLogin($supervisor, $token);
        return $this->response(true, __('message.msg_send_successfully'), $response);
    }

    public function prepareUserData($supervisor, $otp)
    {
        Otp::where(array('user_id' => $supervisor->id, 'type' => 1))->delete();
        Otp::createOtpRecord($supervisor->id, $otp, Carbon::now()->addMinutes(2), 1);
        $supervisor->update(['otp' => $otp]);
    }

    public function restrictedUser()
    {
        return $this->response(false, __('message.msg_error_restricted_user'), null, 401);
    }

    public function recaptchaVerify(Request $request)
    {


        $recaptchaResponse = $this->recaptcha->verifyResponse($request->recaptcha_token);

        if (!($recaptchaResponse["success"] ?? false)) {
            return $this->response(false, __('message.msg_error_captcha'), $recaptchaResponse, 400);
        }
    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => __('Logged out successfully.')]);
    }

    public function handleFailedLogin($user_data, Request $request)
    {

        $faild_login = Setting::get_by_name('admin_failed_login');
        $site_name = Setting::get_by_name('site_name');
        $system_sms_sender = Setting::get_by_name('system_sms_sender');
        $receiver_number = Setting::get_by_name('receiver_number');
        $time = date("Y-m-d H:i:s");
        $ip = $request->ip();
        $receiver_email = Setting::get_by_name('receiver_email');
        $Emailsubject = __('message.lbl_failed_login');
        $Loginmessage = __('message.msg_info_messages_failed_login');
        $array_params = array(
            '{site_name}' => $site_name,
            '{time}' => $time,
            '{ip}' => $ip,
        );
        if (!empty($array_params)) {
            $param_names = array_keys($array_params);
            $param_values = array_values($array_params);
            $Loginmessage = str_replace($param_names, $param_values, $Loginmessage);
        }

        if ($faild_login == "EMAIL" || $faild_login == "BOTH") {

            $this->SendLoginNotification->sendEmailNotification($receiver_email, $Emailsubject, $site_name, $Loginmessage);
        }

        if ($faild_login == "SMS" || $faild_login == "BOTH") {
            $this->SendLoginNotification->sendSmsNotification($system_sms_sender, $receiver_number, $Loginmessage, 'admin');
        }

        AdminFailedLogin::InsertByArray([
            'username' => $user_data->username,
            'password' => $request->password,
            'ip' => $request->ip(),
            'date' => now(),
        ]);
        Log::error(['email' => $user_data->email ?? 'unknown', 'message' => 'Failed login attempt.']);
        return response()->json(['message' => __('Invalid login credentials')], 401);
    }

    public function sendNotifications($user, $activation_code, $request)
    {
        $announcement = announcement::get_by_name('admin_login');
        $announcement2 = $user->otp_from;
        $Loginmessage = $announcement->text_sms;
        $array_params = array(
            '{otp_code}' => $activation_code,
        );
        if (!empty($array_params)) {
            $param_names = array_keys($array_params);
            $param_values = array_values($array_params);
            $Loginmessage = str_replace($param_names, $param_values, $Loginmessage);
        }
        $Emailsubject = $announcement->title_ar . " - " . $announcement->title_en;

        if ($announcement2 == 2 || $announcement2 == 0 || $announcement2 == null) {
            //  sending email
        $view = request()->header('accept-language') === 'en' ? 'mail.login_otp_en' : 'mail.login_otp_ar';

            $this->SendLoginNotification->sendEmailNotification(
                $user->getAttribute('email'),
                $Emailsubject,
                'Dreams SMS',
                $Loginmessage,
                $view,
                null,
                null,
                null,
                null,
                $activation_code,
                'Login'
            );
        }
        if ($announcement2 == 1 || $announcement2 == 0) {
            $numbers = $user->number;
            $system_sms_sender = Setting::get_by_name('system_sms_sender');
            //  sending sms
            $this->SendLoginNotification->sendSmsNotification($system_sms_sender, $numbers, $Loginmessage, 'admin');
        }
    }
}
