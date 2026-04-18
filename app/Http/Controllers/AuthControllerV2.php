<?php

namespace App\Http\Controllers;


use App\Class\payment\SmsService;
use App\Http\Responses\Login;
use App\Http\Responses\Me;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\User;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\JWT;
use App\Http\Controllers\Settings\EmailController;
use Illuminate\Support\Facades\Log;
use App\Models\Country;
use Illuminate\Support\Facades\Auth;
use App\Models\Setting;
use App\Models\Otp;
use Illuminate\Support\Carbon;
use App\Services\Sms;
use App\Enums\Service as EnumService;
use App\Models\PasswordHistory;
use App\Models\announcement;
use App\Models\UserFailedLogin;
use App\Models\UserOtp;
use App\Services\UserLoginService;
use App\Services\SendLoginNotificationService;
use App\Services\UserRegisterService;

class AuthControllerV2 extends SmsApiController implements HasMiddleware
{


    protected $sms;
    protected $UserLoginService;
    protected $SendLoginNotification;
    protected $UserRegisterService;

    public function __construct(SMS $sms, UserLoginService $UserLoginService, UserRegisterService $UserRegisterService, SendLoginNotificationService $SendLoginNotification)
    {
        parent::__construct($sms);
        $this->UserLoginService = $UserLoginService;
        $this->SendLoginNotification = $SendLoginNotification;
        $this->UserRegisterService = $UserRegisterService;
    }


    public static function middleware(): array
    {
        return [
            new Middleware(
                'auth:api',
                except: ['register', 'login', 'countries', 'inactive', 'payment.urway.callback', 'recaptch']
            )
        ];
    }


    /**
     * Handle user registration request.
     *
     * @param Request $request
     * @return JsonResponse
     */


    /**
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     operationId="register",
     *     tags={"AUTH"},
     *     @OA\Parameter(
     *         name="Language",
     *         in="header",
     *         description="Language",
     *         required=false,
     *         @OA\Schema(
     *             type="string",
     *             format="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         description="Input data format",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"first_name", "last_name", "email", "phone", "password"},
     *                 @OA\Property(
     *                     property="first_name",
     *                     description="The first name of the user",
     *                     type="string",
     *                     example="John"
     *                 ),
     *                 @OA\Property(
     *                     property="last_name",
     *                     description="The last name of the user",
     *                     type="string",
     *                     example="Doe"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     description="The email of the user",
     *                     type="string",
     *                     format="email",
     *                     example="john.doe@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="phone",
     *                     description="The phone number of the user",
     *                     type="string",
     *                     example="1234567890"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     description="The password of the user",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="User registered successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object"
     *                 ),
     *                 @OA\Property(
     *                     property="token",
     *                     type="string",
     *                     example="token_example"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Validation error"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="errors",
     *                     type="object",
     *                     additionalProperties={
     *                         "type": "array",
     *                         "items": {
     *                             "type": "string"
     *                         }
     *                     }
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    /*   public function register(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:user',
                'phone' => 'required|string|min:5|max:20',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }

        $user = User::create([
            'first_name' => $request->get('first_name'),
            'last_name' => $request->get('last_name'),
            'email' => $request->get('email'),
            'phone' => $request->get('phone'),
            'password' => Hash::make($request->get('password')),
        ]);

        // TODO: Send Email Verification

        $token = auth('api')->attempt(['email' => $request->get('email'), 'password' => $request->get('password')]);

        return $this->response(true, 'User registered successfully', [
            'user' => $user,
            'token' => $this->getTokenInfo($token),
        ], 201);
    }
*/

    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'username' => 'required|string|min:5|max:50|unique:user',
                'name' => 'required|string|min:4|max:50',
                'email' => 'required|string|email|max:255|unique:user',
                'number' => 'required|numeric',
                'password' => 'required|string|min:8|confirmed',
                'country_id' => 'required|exists:country,id',
                'phone' => 'required|string',
                'address' => 'required|string',

            ]
        );
        //TODO:  must use recaptcha
        if ($validator->fails()) {
            if ($validator->fails()) {
                // Log::error(new ValidatorErrorResponse($validator->errors()->toArray()));
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }
        ////recaptcha validation
        $this->UserLoginService->recaptchaVerify($request);

        $activation_code = rand(10000, 99999);
        //create user
        $user = $this->UserRegisterService->createUser($activation_code, $request);
        //TODO: Send Welcome message to user , text from database
        $this->UserRegisterService->welcomeMessage('user', 'email', $user, $activation_code);
        //Welcome sms for user
        $this->UserRegisterService->welcomeMessage('user', 'sms', $user, $activation_code);
        //Notify to admin with email
        $this->UserRegisterService->welcomeMessage('admin', 'email', $user, $activation_code);

        //TODO: Send notify admin message
        $this->UserRegisterService->welcomeMessage('admin', 'sms', $user, $activation_code);


        ///assign parent permissions
        $this->UserRegisterService->assignPermissions($user);
        $token = auth('api')->attempt(['email' => $request->get('email'), 'password' => $request->get('password')]);
        //  $this->otp($activation_code, $user->id);
        return $this->response(true, __('message.msg_send_successfully'), [
            'user' => $user,
            'token' => $this->getTokenInfo($token),
        ], 201);
    }
    /**
     * Handle user login request.
     *
     * @param Request $request
     * @return JsonResponse
     */

    /**
     * @OA\Post(
     *     path="/api/auth/login",
     *     summary="Login a user",
     *     operationId="login",
     *     tags={"AUTH"},
     *      @OA\Parameter(
     *          name="Language",
     *          in="header",
     *          description="Language",
     *          required=false,
     *          @OA\Schema(
     *              type="string",
     *              format="string"
     *          )
     *      ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="username", type="string", format="string", example="menna"),
     *             @OA\Property(property="password", type="string", format="password", example="password")
     *         )
     *     ),
     *     @OA\Response(
     *          response=201,
     *          description="User Logged in successfully",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=true
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="User Logged in successfully"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="user",
     *                      type="object"
     *                  ),
     *                  @OA\Property(
     *                      property="token",
     *                      type="string",
     *                      example="token_example"
     *                  )
     *              )
     *          )
     *      ),@OA\Response(
     *          response=400,
     *          description="Validation error",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=false
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="Validation error"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="object",
     *                  @OA\Property(
     *                      property="errors",
     *                      type="object",
     *                      additionalProperties={
     *                          "type": "array",
     *                          "items": {
     *                              "type": "string"
     *                          }
     *                      }
     *                  )
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response=401,
     *          description="UnAuthorized",
     *          @OA\JsonContent(
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean",
     *                  example=false
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string",
     *                  example="Unauthorized"
     *              )
     *          )
     *      )
     *  )
     * )
     * @throws \Exception
     */



    public function login(Request $request): JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|exists:user,username',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }
        ////recaptcha validation
       $this->UserLoginService->recaptchaVerify($request);

        $username = $request->get('username');
        $password = $request->get('password');
        $user_data = User::getByUsername($username);

        if (empty($user_data)) {
            Log::error([$username, __('message.msg_error_login')]);
            return $this->response(false, __('message.msg_error_login'), null, 401);
        }

        if (!Hash::check($password, $user_data->password)) {
            return $this->UserLoginService->handleFailedLogin($user_data, $username, $password, $request);
        }

        if ($user_data->blocked) {
            return  $this->UserLoginService->handleBlockedUser($user_data, $username);
        }

        if ($user_data->suspended_at) {
            return  $this->UserLoginService->handleSuspendedUser($user_data, $username);
        }

        return  $this->UserLoginService->handleSuccessfulLogin($user_data, $username, $password, $request);
    }


    public function activateAccount($user, $activation_code)
    {
        if ($user->activation_code != $activation_code) {
            return new JsonResponse(['message' => __('message.msg_error_invalid_activation')], 400);
        }
        // Activate the user account
        $user->update(['activation_code' => null,'otp'=>null, 'active' => 1]);
    }

    public function verifyActivationCode(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|string|email|max:255|exists:user',
                'activation_code' => 'required|string|',
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                // Log::error(new ValidatorErrorResponse($validator->errors()->toArray()));
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }

        $user = User::find(Auth::id());

        if ($user->active == 0) {
            $this->activateAccount($user, $request->activation_code);
            return $this->response(true, __('message.msg_Active_successfully'), [
                'user' => $user
            ]);
        }

        $otpRecord = Otp::getOtpRecord($request->activation_code, Auth::id(),0);

        if (!$otpRecord) {
            return $this->response(false, __('message.msg_error_invalid_activation'), null, 400);
        }
        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            Otp::where(array('otp' => $request->activation_code, 'user_id' => Auth::id()))->delete();
            $user->update(['otp' => null]);
            return $this->response(false, __('message.msg_error_expired_activation'), null, 400);
        }

        Otp::where(array('otp' => $request->activation_code, 'user_id' => Auth::id()))->delete();
        $user->update(['otp' => null]);
        return $this->response(true, __('message.msg_Active_successfully'), [
            'user' => $user
        ]);
    }

    public function ResendverifyActivationCode(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'email' => 'required|string|email|max:255|exists:user'
            ]
        );

        $user = User::find(Auth::id());
        $activation_code = rand(10000, 99999);

        // update activation_code to  user account
        $user->update(['otp' => $activation_code]);
        $this->otp($activation_code, $user->id);
        $body = "رمز الدخول " . $activation_code;
        // Dispatch the email sending job
        $this->SendLoginNotification->sendEmailNotification($request->email, 'تسجيل المستخدم - User Register', 'Dreams SMS', $body);

        // Call the sendSmsAdmin method
        $this->sendSmsAdmin('Dreams', $user->number, $body);

        return $this->response(true, __('message.msg_send_successfully'), [
            'user' => $user
        ], 201);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     *
     * @return array
     */
    protected function getTokenInfo(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->guard('api')->factory()->getTTL() * 60
        ];
    }

    public function countries()
    {
        $countries = Country::all();
        return response()->json(['countries' => $countries], 200);
    }

    public function inactive()
    {
        return $this->response(false, __('message.message_error_not_active'), null, 403);
    }


    public function logout()
    {
        $user = auth('api')->user();
        $user->update(['otp' => null]);
        Auth::logout();
        return $this->response(true, __('message.msg_logged_successfully'), 201);
    }

    public function otp($otp, $user_id)
    {
        $expiresAt = Carbon::now()->addMinutes(2); // Set expiry time to 2 minutes from now
        Otp::create([
            'user_id' => $user_id,
            'otp' => $otp,
            'expires_at' => $expiresAt,
            'type'=>0
        ]);

        $otpRecord = Otp::getOtpRecord($otp, $user_id,0);
        if (!$otpRecord) {
            return $this->response(false, __('message.msg_error_invalid_otp'), null, 400);
        }
        if (Carbon::now()->greaterThan($otpRecord->expires_at)) {
            return $this->response(false, __('message.msg_error_otp_expired'), null, 400);
        }
        return $this->response(true, __('message.msg_valid_otp'), null,201);
    }

    public function me(Request $request)
    {
        $authenticatedUser = auth('api')->user();

        $user = User::find($authenticatedUser->getAuthIdentifier());

        $response = new Me($user, $request);

        return $this->response(true, __('message.msg_send_successfully'), $response);
    }
    public function recaptch()
    {
        return $this->UserLoginService->recaptch();
    }
}
