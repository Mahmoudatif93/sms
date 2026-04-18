<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Helpers\MailHelper;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PasswordResetController extends  BaseApiController implements HasMiddleware
{
    /**
     * Handle user registration request.
     *
     * @param Request $request
     * @return JsonResponse
     */



    public static function middleware(): array
    {
        return [
            new Middleware(
                'auth:api',
                except: ['sendResetLinkEmail', 'update_password', 'resetPassword', 'changepassword']
            )
        ];
    }

    public function sendResetLinkEmail(Request $request)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|email|exists:user'
            ]
        );
        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );
        return $this->response(true, 'We have emailed your password reset link.');
    }


    public function resetPassword(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'token' => 'required',
                'email' => 'required|email|exists:user'
            ]
        );
        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }
        $data['token'] = $request->token;
        $data['email'] = $request->email;
        return redirect()->to(('https://dev-portal.dreams.sa/new-password') . '?token=' . $request->token . '&email=' . $request->email);
    }


    public function update_password(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|confirmed|min:8',
        ]);

        $reset = DB::table('password_resets')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->token, $reset->token)) {
            return $this->response(false, 'The token is invalid or expired.', 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return $this->response(false, 'User not found.', 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        DB::table('password_resets')->where('email', $request->email)->delete();

        return $this->response(true, 'تم عمل تعديل للرقم السري');
    }



    public function changepassword(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'old_password' => 'required',
                'email' => 'required|email|exists:user',
                'password' => 'required|min:8|confirmed',

            ]
        );
        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }
        $user = Auth::user();
        if (!Hash::check($request->old_password, $user->password)) {
            return $this->response(false, 'The provided password does not match your current password', 400);
        }
        $user->password = Hash::make($request->password);
        $user->save();
        $token = $request->header('Authorization');
        // If the token is prefixed with 'Bearer ', remove it
        if (strpos($token, 'Bearer ') === 0) {
            $token = substr($token, 7);
        }
        // Reset the password
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation') + ['token' => $token],
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $this->response(true, 'تم عمل تعديل للرقم السري');
    }
}
