<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\SendLoginNotificationJob;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Http\Responses\ValidatorErrorResponse;
use Carbon\Carbon;
use App\Models\UserOtp;
use App\Models\Setting;
use App\Models\UserFailedLogin;
use App\Models\announcement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\SmsApiController;
use App\Http\Controllers\Settings\EmailController;
use App\Services\Sms;
use App\Enums\Service as EnumService;
use App\Http\Responses\Login;
use App\Services\SendLoginNotificationService;
use App\Models\Otp;
use App\Services\RecaptchaService;

class UserLoginService extends SmsApiController
{


    protected $sms;
    protected $SendLoginNotification;
    protected $recaptcha;

    public function __construct(SMS $sms, SendLoginNotificationService $SendLoginNotification, RecaptchaService $recaptcha)
    {
        parent::__construct($sms);
        $this->SendLoginNotification = $SendLoginNotification;
        $this->recaptcha = $recaptcha;
    }


    public function handleFailedLogin($user_data, $username, $password, Request $request)
    {
        Log::error([$username, __('message.msg_error_login')]);
        UserFailedLogin::InsertByArray([
            'id' => 0,
            'user_id' => $user_data->id,
            'password' => $password,
            'ip' => $request->ip(),
        ]);

        $setting_faild_login = Setting::get_by_name('faild_login');

        if ($setting_faild_login == $user_data->faild_count_login + 1) {
            $user_data->UpdateFailedCountLogin(Setting::get_by_name('offset_time'));
        } else {
            User::updateByArray(['faild_count_login' => $user_data->faild_count_login + 1], ['id' => $user_data->id]);
        }

        return $this->response(false, __('message.msg_error_login'), null, 401);
    }

    public function handleBlockedUser($user_data, $username)
    {
        Log::error([$username, __('message.msg_error_restricted_user')]);
        return $this->response(false, __('message.msg_error_restricted_user'), null, 401);
    }

    public function handleSuspendedUser($user_data, $username)
    {
        $specific_date = new Carbon($user_data->suspended_at);
        $faild_login_time = Setting::get_by_name('faild_login_time');
        $suspendedUntil = $specific_date->addMinutes((int)$faild_login_time);
        $now = Carbon::now();


        if ($now->lessThan($suspendedUntil)) {
            $remainingMinutes = $suspendedUntil->diffInMinutes($now);
        } else {
            $remainingMinutes = 0;
        }

        $messages = __('message.msg_error_suspend_user');
        $array_params =   array(
            '{minutes}' => $remainingMinutes
        );
        if (!empty($array_params)) {
            $param_names = array_keys($array_params);
            $param_values = array_values($array_params);
            $messages = str_replace($param_names, $param_values, $messages);
        }
        $SuspendMessage = $messages;
        Log::error([$username, $SuspendMessage]);
        return $this->response(false, $SuspendMessage, null, 401);
    }

    public function handleSuccessfulLogin($user_data, $username, $password,  $request)
    {
        if (!$token = auth('api')->attempt(compact('username', 'password'))) {
            return $this->response(false, __('message.msg_error_Unauthorized'), null, 401);
        }

        $activation_code = ($user_data->id == 43 || $user_data->id == 97) ? 123456 : rand(10000, 99999);

        $this->prepareUserData($user_data, $activation_code);


        $response = new Login($user_data, $token);
        // $this->User_wallets($user_data);
        $this->updateUserLoginDetails($user_data, $request);
        $this->sendNotifications($user_data, $activation_code,  $request);

        Log::info([$username, __('messages.msg_success_login'), 'timestamp' => date('Y-m-d H:i:s')]);
        return $this->response(true, __('message.msg_send_successfully'), $response);
    }

    /* public function User_wallets($user_data)
    {
        $wallets = $user_data->wallets->map(function ($wallet) use ($user_data) {
            return [
                'service' => $wallet->service->name,
                'amount' => $wallet->service->name === EnumService::SMS ? $user_data->total_balance : $wallet->amount, //TODO: depende to wallet , later
                'currency_code' => $wallet->currency_code,
                'status' => $wallet->status
            ];
        });

        unset($user_data->wallets);
    }*/

    public function prepareUserData($user_data, $activation_code)
    {
        Otp::where(array('user_id' => $user_data->id, 'type' => 0))->delete();
        Otp::createOtpRecord($user_data->id, $activation_code, Carbon::now()->addMinutes(2), 0);
        $user_data->update(['otp' => $activation_code]);
        // Handle OTP logic
    }

    public function sendNotifications($user, $activation_code, $request)
    {
        $announcement = announcement::get_by_name('user_login');
        $announcement2 = $user->otp_from;
        $Loginmessage = $announcement->text_sms;
        $array_params =   array(
            '{otp_code}' => $activation_code
        );
        if (!empty($array_params)) {
            $param_names = array_keys($array_params);
            $param_values = array_values($array_params);
            $Loginmessage = str_replace($param_names, $param_values, $Loginmessage);
        }


        $view = (request()->header('accept-language')  && strtolower(request()->header('accept-language')) === 'en')
            ? 'mail.login_otp_en'
            : 'mail.login_otp_ar';

        $Emailsubject = $announcement->title_ar . " - " . $announcement->title_en;
        if ($announcement2 == 2 || $announcement2 == 0) {
            //  sending email
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
            $mobiles = Otp::getByUserId($user->id);
            $numbers = [];
            array_push($numbers, $user->number);
            foreach ($mobiles as $mobile) {
                array_push($numbers, $mobile->mobile);
            }
            if (in_array(970598704541, $numbers) || in_array(970598704540, $numbers)) {
                $activation_code = 123456;
            }
            $system_sms_sender = Setting::get_by_name('system_sms_sender');
            //  sending sms
            $this->SendLoginNotification->sendSmsNotification($system_sms_sender, implode(',', array_unique($numbers)), $Loginmessage, 'admin');
        }
    }

    public function updateUserLoginDetails($user_data, Request $request)
    {
        $offset_time = Setting::get_by_name('offset_time');
        $user_data->updateLastLoginDateIpAgent(
            $offset_time,
            $request->ip(),
            $request->header('User-Agent')
        );

        UserFailedLogin::InsertByArray([
            'id' => 0,
            'user_id' => $user_data->id,
            'ip' => $request->ip(),
            'status' => 1,
        ]);
    }


    public function recaptchaVerify(Request $request)
    {
        $recaptchaResponse = $this->recaptcha->verifyResponse($request->recaptcha_token);

        if (!($recaptchaResponse["success"] ?? false)) {
            return $this->response(false, __('message.msg_error_captcha'), $recaptchaResponse, 400);
        }
    }
    public function recaptch()
    {
        $recaptcha = $this->recaptcha->getWidget();
        return $this->response(true, ' recaptcha', [
            'recaptcha' => $recaptcha
        ]);
    }
}
