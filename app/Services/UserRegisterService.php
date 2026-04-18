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
use App\Models\PasswordHistory;
use App\Models\UserFailedLogin;
use App\Models\announcement;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\SmsApiController;
use App\Http\Controllers\Settings\EmailController;
use App\Services\Sms;
use App\Models\Menu;
use App\Models\Permission;
use App\Enums\Service as EnumService;
use App\Http\Responses\Login;
use App\Services\SendLoginNotificationService;
use App\Models\Service as MService;
use App\Models\WalletAssignment;
use App\Models\Organization;
use App\Models\Wallet;
use App\Jobs\LogWorkspaceBalance;
use App\Traits\WalletManager;
use App\Contracts\NotificationManagerInterface;

class UserRegisterService extends SmsApiController
{
    use WalletManager;
    protected $sms;
    protected $SendLoginNotification;
    protected $notificationManager;

    public function __construct(
        SMS $sms,
        SendLoginNotificationService $SendLoginNotification,
        NotificationManagerInterface $notificationManager
    ) {
        parent::__construct($sms);
        $this->SendLoginNotification = $SendLoginNotification;
        $this->notificationManager = $notificationManager;
    }
    public function createUser($activation_code, $request)
    {
        $user = User::create([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'number' => str_replace(' ', '', $request->number),
            'password' => Hash::make($request->password),
            'country_id' => $request->country_id,
            'parent_id' => $request->manager_id,
            'phone' => str_replace(' ', '', $request->number),
            'address' => $request->address,
            'activation_code' => $activation_code,
            'last_login_ip' => \Request::ip(),
            'invitation_key' => bin2hex(random_bytes(16)),

        ]);
        //TODO: save password in history
        PasswordHistory::create([
            'id' => 0,
            'user_id' => $user->id,
            'password' => Hash::make($request->password), // Save hashed old password
        ]);
        return $user;
    }


    public function welcomeMessage($receiverType, $messageType, $user, $activation_code, $request)
    {
        $announcement = announcement::get_by_name('user_welcoming');
        $site_name = Setting::get_by_name('site_name');
        $system_sms_sender = Setting::get_by_name('system_sms_sender');
        $admin_notify_announ = announcement::get_by_name('new_user_notify_admin');
        $receiver_email = Setting::get_by_name('receiver_email');
        $receiver_number = Setting::get_by_name('receiver_number');

        if ($receiverType == 'user' && $messageType == 'email') {
            $this->welcomeUserEmail($announcement, $site_name, $user, $activation_code, $request);
        }
        if ($receiverType == 'user' && $messageType == 'sms') {
            $this->welcomeUserSms($announcement, $site_name, $user, $activation_code, $system_sms_sender);
        }

        if ($receiverType == 'admin' && $messageType == 'email') {
            $this->welcomeAdminEmail($admin_notify_announ, $announcement, $site_name, $user, $activation_code, $receiver_email);
        }

        if ($receiverType == 'admin' && $messageType == 'sms') {
            $this->welcomeAdminSms($admin_notify_announ, $announcement, $site_name, $user, $activation_code, $system_sms_sender, $receiver_number);
        }
        // Send Email Verification
        $body = "رمز الدخول " . $activation_code;
        $view = request()->header('accept-language') === 'en' ? 'mail.registration_otp_en' : 'mail.registration_otp_ar';
        $this->SendLoginNotification->sendEmailNotification(
            $user->getAttribute('email'),
            'تسجيل المستخدم - User Register',
            'Dreams SMS',
            $body,
            $view,
            null,
            null,
            null,
            null,
            $activation_code,
            'registration'
        );
        $this->sendSmsAdmin('Dreams', $user->number, $body);
    }

    public function welcomeUserEmail($announcement, $site_name, $user, $activation_code)
    {
        if ($announcement->media == "EMAIL" || $announcement->media == "BOTH") {
            $subject = $announcement->title_ar . " - " . $announcement->title_en;
            $message = $announcement->text_email;
            $array_params =   array(
                '{site_name}' => $site_name,
                'username' => $user->username,
                'number' => $user->number,
                'activation_code' => $activation_code
            );
            if (!empty($array_params)) {
                $param_names = array_keys($array_params);
                $param_values = array_values($array_params);
                $message = str_replace($param_names, $param_values, $message);
            }
                    $view = request()->header('accept-language') === 'en' ? 'mail.registration_otp_en' : 'mail.registration_otp_ar';


            $this->SendLoginNotification->sendEmailNotification(
                $user->getAttribute('email'),
                $subject,
                'Dreams SMS',
                $message,
                $view,
                null,
                null,
                null,
                null,
                $activation_code,
                'registration'
            );
        }
    }

    public function welcomeUserSms($announcement, $site_name, $user, $activation_code, $system_sms_sender)
    {
        if ($announcement->media == "SMS" || $announcement->media == "BOTH") {
            if ($announcement->budget == "ADMIN") {
                $messageadmin = $announcement->text_sms;
                $array_params =   array(
                    '{site_name}' => $site_name,
                    'username' => $user->username,
                    'number' => $user->number,
                    'activation_code' => $activation_code
                );
                if (!empty($array_params)) {
                    $param_names = array_keys($array_params);
                    $param_values = array_values($array_params);
                    $messageadmin = str_replace($param_names, $param_values, $messageadmin);
                }
                $this->SendLoginNotification->sendSmsNotification($system_sms_sender, $user->number, $messageadmin, 'admin');
            } else {
                $messageadmin = $announcement->text_sms;
                $array_params =   array(
                    '{site_name}' => $site_name,
                    'username' => $user->username,
                    'number' => $user->number,
                    'activation_code' => $activation_code
                );
                if (!empty($array_params)) {
                    $param_names = array_keys($array_params);
                    $param_values = array_values($array_params);
                    $messageadmin = str_replace($param_names, $param_values, $messageadmin);
                }
                $this->SendLoginNotification->sendSmsNotification($system_sms_sender, $user->number, $messageadmin, 'user', $user->id);
            }
        }
    }


    public function welcomeAdminEmail($admin_notify_announ, $announcement, $site_name, $user, $activation_code, $receiver_email)
    {
        if ($admin_notify_announ->media == "EMAIL" || $admin_notify_announ->media == "BOTH") {
            if ($admin_notify_announ->budget == "ADMIN") {
                $subject = $admin_notify_announ->title_ar . " - " . $admin_notify_announ->title_en;
                $message = $announcement->text_email;
                $array_params =   array(
                    '{site_name}' => $site_name,
                    'username' => $user->username,
                    'number' => $user->number,
                    'activation_code' => $activation_code
                );
                if (!empty($array_params)) {
                    $param_names = array_keys($array_params);
                    $param_values = array_values($array_params);
                    $message = str_replace($param_names, $param_values, $message);
                }

                $this->SendLoginNotification->sendEmailNotification($receiver_email, $subject, 'Dreams SMS', $message);
            }
        }
    }


    public function welcomeAdminSms($admin_notify_announ, $announcement, $site_name, $user, $activation_code, $system_sms_sender, $receiver_number)
    {
        if ($admin_notify_announ->media == "SMS" || $admin_notify_announ->media == "BOTH") {
            if ($admin_notify_announ->budget == "ADMIN") {
                $messageadmin = $announcement->text_sms;
                $array_params =   array(
                    '{site_name}' => $site_name,
                    'username' => $user->username,
                    'number' => $user->number,
                    'activation_code' => $activation_code
                );
                if (!empty($array_params)) {
                    $param_names = array_keys($array_params);
                    $param_values = array_values($array_params);
                    $messageadmin = str_replace($param_names, $param_values, $messageadmin);
                }
                $this->sendSmsAdmin($system_sms_sender, $receiver_number, $messageadmin);
            }
        }
    }

    public function assignPermissions($user)
    {

        $menues = Menu::all();
        if (!empty($menues)) {
            foreach ($menues as $menu) {
                Permission::updateOrCreate(
                    ['menu_id' => $menu->id, 'role_id' => 1, 'user_id' => $user->id],
                    ['can_access' => 1]
                );
            }
        }
    }


    public function createMainWallets($organization)
    {
        //Todo: create Other qouta
        $serviceOtherID = MService::where('name', EnumService::OTHER)->value('id');

        $orgOtherWallet = $organization->wallets()->create([
            'user_id' => $organization->owner_id,
            'service_id' => $serviceOtherID,
            'amount' => 0.00,
            'type' => 'primary',
            'status' => 'active',
            'name' => 'Main'
        ]);
        WalletAssignment::create([
            'wallet_id' => $orgOtherWallet->id,
            'assignable_type' => Organization::class,
            'assignable_id' => $organization->id,
            'assignment_type' => 'primary'
        ]);

        $serviceSmsId = MService::where('name', EnumService::SMS)->value('id');
        $freePoints = Setting::get_by_name('free_balance');
        $orgSmsWallet =  $organization->wallets()->create([
            'user_id' => $organization->owner_id,
            'service_id' => $serviceSmsId,
            'amount' => 0.00,
            'type' => 'primary',
            'sms_point' => 0,
            'status' => 'active',
            'name' => 'Main - SMS'
        ]);

        WalletAssignment::create([
            'wallet_id' => $orgSmsWallet->id,
            'assignable_type' => Organization::class,
            'assignable_id' => $organization->id,
            'assignment_type' => 'primary',
            'status' => 'active'
        ]);
        $this->changeBalance($orgSmsWallet, floatval($freePoints), EnumService::SMS, 'Registration Givt', 0, Carbon::now()->addYear());
    }

    /**
     * Send registration notifications using the new centralized notification system
     */
    public function sendRegistrationNotifications(User $user, int $activation_code, Request $request): void
    {
        try {
            $locale = $request->header('accept-language') === 'en' ? 'en' : 'ar';
            $siteName = Setting::get_by_name('site_name') ?? 'Dreams';
            $registrationTime = now()->format('Y-m-d H:i:s');

            // 1. Send OTP to user for account activation
            $this->sendRegistrationOTPToUser($user, $activation_code, $locale, $siteName, $registrationTime);

            // 2. Send welcome message to user
            $this->sendWelcomeMessageToUser($user, $locale, $siteName);

            // 3. Notify admin about new user registration
            $this->sendNewUserNotificationToAdmin($user, $locale, $siteName, $registrationTime);

        } catch (\Exception $e) {
            \Log::error('Error sending registration notifications', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send OTP to user for account activation
     */
    private function sendRegistrationOTPToUser(User $user, int $activation_code, string $locale, string $siteName, string $registrationTime): void
    {
        try {
            $recipients = [['type' => 'user', 'identifier' => $user->id]];
            $channels = ['sms', 'email']; // Send via both channels for registration

            $templateVariables = [
                'otp_code' => $activation_code,
                'user_name' => $user->name ?? $user->username,
                'username' => $user->username,
                'site_name' => $siteName,
                'number' => $user->number
            ];

            // Use admin sender for registration OTP
            $options = [
                'sender_name' => Setting::get_by_name('system_sms_sender') ?? 'Dreams',
                'sender_type' => 'admin'
            ];

            $result = $this->notificationManager->sendFromTemplate(
                'registration_otp',
                $recipients,
                $templateVariables,
                $channels,
                $options
            );

            if ($result['success']) {
                \Log::info('Registration OTP sent successfully', [
                    'user_id' => $user->id,
                    'channels' => $channels,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                \Log::warning('Failed to send registration OTP', [
                    'user_id' => $user->id,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error sending registration OTP to user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send welcome message to user
     */
    private function sendWelcomeMessageToUser(User $user, string $locale, string $siteName): void
    {
        try {
            $recipients = [['type' => 'user', 'identifier' => $user->id]];
            $channels = ['email']; // Welcome message via email only

            $templateVariables = [
                'user_name' => $user->name ?? $user->username,
                'site_name' => $siteName
            ];

            $options = [
                'sender_type' => 'admin'
            ];

            $result = $this->notificationManager->sendFromTemplate(
                'welcome_user',
                $recipients,
                $templateVariables,
                $channels,
                $options
            );

            if ($result['success']) {
                \Log::info('Welcome message sent successfully', [
                    'user_id' => $user->id,
                    'message_id' => $result['message_id'] ?? null
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error sending welcome message to user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send new user notification to admin
     */
    private function sendNewUserNotificationToAdmin(User $user, string $locale, string $siteName, string $registrationTime): void
    {
        try {
            $adminEmail = 'malthwabteh@gmail.com';//Setting::get_by_name('receiver_email');
            $adminNumber = Setting::get_by_name('receiver_number');

            if (!$adminEmail && !$adminNumber) {
                \Log::warning('No admin contact information found for new user notification');
                return;
            }

            $recipients = [];
            $channels = [];

            // Add admin email if available
            if ($adminEmail) {
                $recipients[] = ['type' => 'email', 'identifier' => $adminEmail];
                $channels[] = 'email';
            }

            // Add admin phone if available
            if ($adminNumber) {
                $recipients[] = ['type' => 'phone', 'identifier' => $adminNumber];
                $channels[] = 'sms';
            }

            $templateVariables = [
                'user_name' => $user->name ?? $user->username,
                'username' => $user->username,
                'site_name' => $siteName,
                'number' => $user->number,
                'email' => $user->email,
                'registration_time' => $registrationTime
            ];

            $options = [
                'sender_name' => Setting::get_by_name('system_sms_sender') ?? 'Dreams',
                'sender_type' => 'admin'
            ];
            $result = $this->notificationManager->sendFromTemplate(
                'new_user_admin_notification',
                $recipients,
                $templateVariables,
                $channels,
                $options
            );

            if ($result['success']) {
                \Log::info('Admin notification sent successfully for new user', [
                    'user_id' => $user->id,
                    'admin_channels' => $channels,
                    'message_id' => $result['message_id'] ?? null
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error sending admin notification for new user', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
