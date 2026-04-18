<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BalanceLogStatus;
use App\Enums\Service as EnumService;
use App\Enums\WalletTransactionType;
use App\Helpers\Sms\EncryptionHelper;
use App\Jobs\LogUserBalance;
use Auth;
use Database\Factories\UserFactory;
use DateTime;
use Eloquent;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Passport\HasApiTokens;
use App\Notifications\CustomResetPassword;

//use Spatie\Permission\Models\Permission;
//use Spatie\Permission\Models\Role;

//use Spatie\Permission\Traits\HasRoles;

/**
 *
 *
 * @property int $id
 * @property string $first_name
 * @property string $username
 * @property string $last_name
 * @property string $phone
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property mixed $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static UserFactory factory($count = null, $state = [])
 * @method static Builder|User newModelQuery()
 * @method static Builder|User newQuery()
 * @method static Builder|User query()
 * @method static Builder|User whereCreatedAt($value)
 * @method static Builder|User whereEmail($value)
 * @method static Builder|User whereEmailVerifiedAt($value)
 * @method static Builder|User whereFirstName($value)
 * @method static Builder|User whereId($value)
 * @method static Builder|User whereLastName($value)
 * @method static Builder|User wherePassword($value)
 * @method static Builder|User wherePhone($value)
 * @method static Builder|User whereRememberToken($value)
 * @method static Builder|User whereUpdatedAt($value)
 * @property string|null $name
 * @property string|null $number
 * @property int|null $country_id
 * @property string|null $reg_ip
 * @property string|null $address
 * @property string|null $reg_date
 * @property string|null $last_login_date
 * @property string|null $last_login_ip
 * @property int|null $blocked
 * @property int|null $active
 * @property string|null $activation_code
 * @property float|null $spent_balance
 * @property float|null $credit_limit
 * @property string|null $balance_expire_date
 * @property int $can_trans_balance
 * @property int|null $unlimited_senders
 * @property int $reseller
 * @property int|null $parent_id
 * @property int $can_send_ad
 * @property string|null $notification_number
 * @property int|null $notification_limit
 * @property int|null $notification_status
 * @property int|null $notification_has_sent
 * @property string|null $granted_group_ids
 * @property string|null $granted_sender_ids
 * @property string|null $lang
 * @property int|null $login_notify
 * @property int $delivery_reports
 * @property int $use_app
 * @property string|null $otp
 * @property int|null $send_bloks_status
 * @property string|null $suspended_at
 * @property int $faild_count_login
 * @property int $is_hidden
 * @property int|null $allow_url
 * @property int|null $otp_from
 * @property int|null $delivery_status
 * @property int|null $is_school
 * @property string|null $secret_key
 * @property string|null $password_expiration_at
 * @property int|null $can_use_plans
 * @property int|null $is_active_white_ip
 * @property int|null $is_international
 * @property int|null $minimum_plan_price_per_sms
 * @property float|null $sms_price
 * @property string|null $invitation_key
 * @property int|null $linksshortcut
 * @property string|null $domain
 * @property string|null $workspace_id
 * @property-read Collection<int, BalanceLog> $balanceLogs
 * @property-read int|null $balance_logs_count
 * @property-read Collection<int, BusinessManagerAccount> $businessManagerAccounts
 * @property-read int|null $business_manager_accounts_count
 * @property-read Collection<int, Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read int|null $plan_users_count
 * @property-read Collection<int, Role> $roles
 * @property-read int|null $roles_count
 * @property-read Collection<int, Wallet> $wallets
 * @property-read int|null $wallets_count
 * @method static Builder|User permission($permissions, $without = false)
 * @method static Builder|User role($roles, $guard = null, $without = false)
 * @method static Builder|User whereActivationCode($value)
 * @method static Builder|User whereActive($value)
 * @method static Builder|User whereAddress($value)
 * @method static Builder|User whereAllowUrl($value)
 * @method static Builder|User whereBalanceExpireDate($value)
 * @method static Builder|User whereBlocked($value)
 * @method static Builder|User whereCanSendAd($value)
 * @method static Builder|User whereCanTransBalance($value)
 * @method static Builder|User whereCanUsePlans($value)
 * @method static Builder|User whereCountryId($value)
 * @method static Builder|User whereCreditLimit($value)
 * @method static Builder|User whereDeliveryReports($value)
 * @method static Builder|User whereDeliveryStatus($value)
 * @method static Builder|User whereDomain($value)
 * @method static Builder|User whereWorkspaceId($value)
 * @method static Builder|User whereFaildCountLogin($value)
 * @method static Builder|User whereGrantedGroupIds($value)
 * @method static Builder|User whereGrantedSenderIds($value)
 * @method static Builder|User whereInvitationKey($value)
 * @method static Builder|User whereIsActiveWhiteIp($value)
 * @method static Builder|User whereIsHidden($value)
 * @method static Builder|User whereIsInternational($value)
 * @method static Builder|User whereIsSchool($value)
 * @method static Builder|User whereLang($value)
 * @method static Builder|User whereLastLoginDate($value)
 * @method static Builder|User whereLastLoginIp($value)
 * @method static Builder|User whereLinksshortcut($value)
 * @method static Builder|User whereLoginNotify($value)
 * @method static Builder|User whereMinimumPlanPricePerSms($value)
 * @method static Builder|User whereName($value)
 * @method static Builder|User whereNotificationHasSent($value)
 * @method static Builder|User whereNotificationLimit($value)
 * @method static Builder|User whereNotificationNumber($value)
 * @method static Builder|User whereNotificationStatus($value)
 * @method static Builder|User whereNumber($value)
 * @method static Builder|User whereOtp($value)
 * @method static Builder|User whereOtpFrom($value)
 * @method static Builder|User whereParentId($value)
 * @method static Builder|User wherePasswordExpirationAt($value)
 * @method static Builder|User whereRegDate($value)
 * @method static Builder|User whereRegIp($value)
 * @method static Builder|User whereReseller($value)
 * @method static Builder|User whereSecretKey($value)
 * @method static Builder|User whereSendBloksStatus($value)
 * @method static Builder|User whereSmsPrice($value)
 * @method static Builder|User whereSpentBalance($value)
 * @method static Builder|User whereSuspendedAt($value)
 * @method static Builder|User whereTotalBalance($value)
 * @method static Builder|User whereUnlimitedSenders($value)
 * @method static Builder|User whereUseApp($value)
 * @method static Builder|User whereUsername($value)
 * @method static Builder|User withoutPermission($permissions)
 * @method static Builder|User withoutRole($roles, $guard = null)
 * @property-read OrganizationUser $pivot
 * @property-read Collection<int, Organization> $organizationMemberships
 * @property-read int|null $organization_memberships_count
 * @property-read Collection<int, Organization> $ownedOrganizations
 * @property-read int|null $owned_organizations_count
 * @property-read Collection<int, IAMRole> $IAMRoles
 * @property-read Collection<string, Workspace> $workspaces
 * @property-read int|null $i_a_m_roles_count
 * @mixin Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    const CREATED_AT = 'reg_date';
    protected $with = ['IAMRoles']; // Replace with your actual table name
    /**
     * @var string
     */
    protected $table = 'user';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'password',
        'name',
        'email',
        'number',
        'country_id',
        'phone',
        'reg_ip',
        'address',
        'reg_date',
        'last_login_date',
        'last_login_ip',
        'blocked',
        'active',
        'TermsConditions',
        'activation_code',
        'total_balance',
        'spent_balance',
        'credit_limit',
        'balance_expire_date',
        'can_trans_balance',
        'can_use_plans',
        'minimum_plan_price_per_sms',
        'unlimited_senders',
        'reseller',
        'parent_id',
        'can_send_ad',
        'notification_number',
        'notification_limit',
        'notification_status',
        'notification_has_sent',
        'notification_admin_has_sent',
        'granted_group_ids',
        'granted_sender_ids',
        'lang',
        'login_notify',
        'delivery_reports',
        'use_app',
        'otp',
        'send_bloks_status',
        'suspended_at',
        'faild_count_login',
        'is_hidden',
        'allow_url',
        'delivery_status',
        'otp_from',
        'is_school',
        'secret_key',
        'password_expiration_at',
        'is_international',
        'invitation_key',
        'is_active_white_ip',
        'sms_price',
        'erp_id',
        'linksshortcut',
        'domain',
        'workspace_id',
    ];
    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    /*protected $hidden = [
        'password',
        'remember_token',
    ];*/

    public function findForPassport(string $username): User
    {
        return $this->where('username', $username)->first();
    }
    public function validateForPassportPasswordGrant(string $password): bool
    {
        return \Hash::check($password, $this->password);
    }

    public static function getAllSubaccounts($parent_id)
    {
        $query = self::select(
            'id',
            'username',
            'name',
            'email',
            'number',
            'address',
            DB::raw("CASE WHEN active = 1 THEN 'Active' ELSE 'Not Active' END as status"),
            'parent_id'
        )
            ->where('parent_id', $parent_id)
            ->get();

        return $query->isNotEmpty() ? $query : false;
    }


    public static function load_sub_data($parent_id, $perPage, $search)
    {

        if ($perPage != null) {
            if ($search != null) {
                $query = User::where('parent_id', $parent_id)
                    ->where(function ($query) use ($search) {
                        $query->where('username', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('number', 'like', '%' . $search . '%');
                    })
                    ->orderBy('id', 'desc')
                    ->paginate($perPage);
            } else {
                $query = User::where('parent_id', $parent_id)
                    ->orderBy('id', 'desc')
                    ->paginate($perPage);
            }
        } else {
            if ($search != null) {
                $query = User::where('parent_id', $parent_id)
                    ->where(function ($query) use ($search) {
                        $query->where('username', 'like', '%' . $search . '%')
                            ->orWhere('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%')
                            ->orWhere('number', 'like', '%' . $search . '%');
                    })
                    ->orderBy('id', 'desc')
                    ->get();
            } else {
                $query = User::where('parent_id', $parent_id)
                    ->orderBy('id', 'desc')
                    ->get();
            }
        }
        return $query;
    }

    public static function updateByArray(array $data, array $conditions)
    {
        return User::where($conditions)->update($data);
    }

    public static function check_parents($parent_id)
    {

        $query = self::select('id', 'username')
            ->where('parent_id', $parent_id)  // Fetch users where parent_id matches
            ->orWhere(function ($query) use ($parent_id) {
                $query->where('id', $parent_id)  // Fetch where id matches
                    ->whereNull('parent_id');  // and parent_id is null
            })
            ->get();

        return $query->isNotEmpty() ? $query : false;
    }

    public static function getByUsername($username)
    {
        // Query the database to find the user by username
        $user = self::where('username', $username)->first();

        // If the user exists, return the user object; otherwise, return null
        return $user ?: null;
    }


    //TODO: delete function not use

    public static function getSuspendedUsers()
    {
        return static::whereNotNull('suspended_at')->get();
    }

    public static function getWarningBalance()
    {
        // Fetch users meeting the warning balance criteria
        $usersToWarn = static::where('active', 1)
            ->where('blocked', 0)
            ->whereNotNull('notification_number')
            ->where('notification_number', '<>', '')
            ->whereNotNull('notification_limit')
            ->whereColumn('total_balance', '<', 'notification_limit')
            ->where('notification_admin_has_sent', 0)
            ->orderBy('id', 'asc')
            ->get();

        // Update notification_admin_has_sent for users who meet the criteria
        if ($usersToWarn->isNotEmpty()) {
            static::whereIn('id', $usersToWarn->pluck('id'))
                ->update(['notification_admin_has_sent' => 1]);
        }

        return $usersToWarn;
    }

    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }

    public function can_use_plans(): bool
    {
        // Assume `can_use_plans` is a boolean column in your users table
        return $this->can_use_plans == 1;
    }

    public function is_dealer(): bool
    {
        // Add your logic here for checking if the user is a dealer
        return $this->reseller == 1;
    }


    // public function getTotalBalanceAttribute($value)
    // {
    //     $cacheKey = 'user_balance_' . $this->id;
    //     return Cache::remember($cacheKey, 600, function () use ($value) { // 3 months
    //         return $value;
    //     });
    // }

    /**
     * Check if the user's balance expiration date has passed.
     *
     * @return bool
     */
    public function checkBalanceExpiredDate(): bool
    {
        return empty($this->balance_expire_date) || $this->balance_expire_date >= now();
    }

    /**
     * Check if the user has any senders.
     *
     * This method queries the `Senders` table to count the number of senders
     * associated with the user. If the count is greater than zero, it means the user
     * has senders. If the count is zero, it means the user does not have any senders.
     *
     * @return bool True if the user has any senders, false otherwise.
     */
    public function check_sender_empty()
    {
        return !Sender::where('user_id', $this->id)->count();
    }

    public function isAllowUrl()
    {
        return $this->allow_url ?? false;
    }

    public function isBalanceExpired()
    {
        return !empty($this->balance_expire_date) && $this->balance_expire_date < date_format(new DateTime(), 'Y-m-d');
    }

    // Assuming a User model exists and it has a relationship with BalanceLog

    public function isAllowSendBlock()
    {
        return $this->send_bloks_status;
    }

    public function changeBalance($pointCount, $reason, $balanceExpireDate = null, $createdBy = null, $price = 0, $status = BalanceLogStatus::ACTIVE, $quota_id = 0)
    {

        return DB::transaction(function () use ($pointCount, $reason, $balanceExpireDate, $createdBy, $price, $status, $quota_id) {
            $user = DB::table('user')->where('id', $this->id)->lockForUpdate()->first();
            $balance = $user->total_balance ?? 0;
            if ($balance + $pointCount >= 0) {
                DB::table('user')->where('id', $this->id)->update(['total_balance' => $balance + $pointCount]);
                dispatch(new LogUserBalance($this, $pointCount, $price, $reason, $balanceExpireDate, $createdBy, $status, $quota_id));
                return true;
            }
            return false;


            // $cacheKey = 'user_balance_' . $this->id;
            // $redis = Redis::connection();

            // while (true) {
            //     $redis->watch($cacheKey);
            //     $balance = $redis->get($cacheKey);
            //     if ($balance === null) {
            //         $user = DB::table('user')->where('id', $this->id)->lockForUpdate()->first();
            //         $balance = $user->total_balance ?? 0;
            //         $redis->multi();
            //         $redis->set($cacheKey, $balance, 'EX', 600); // انتهاء الصلاحية خلال 10 دقائق
            //         $redis->exec();
            //     }

            //     if ($balance + $pointCount >= 0) {
            //         $redis->multi();
            //         $redis->incrBy($cacheKey, $pointCount);
            //         $result = $redis->exec();

            //         if ($result) {
            //             $newBalance = $redis->get($cacheKey);
            //             //TODO: refactor to update in db later
            //             DB::table('user')->where('id', $this->id)->update(['total_balance' => $newBalance]);
            //             dispatch(new LogUserBalance($this, $pointCount, $price, $reason, $balanceExpireDate, $createdBy, $status, $quota_id));
            //             return true;
            //         }
            //     } else {
            //         return false;
            //     }
            //     $redis->unwatch();
            // }
        });
    }

    public function addBalanceCurrency($amount, $description)
    {
        $transaction_type = $amount >= 0 ? WalletTransactionType::CHARGE : WalletTransactionType::USAGE;
        $serviceId = Service::where('name', EnumService::OTHER)->value('id');
        $wallet = Wallet::updateOrCreate(
            [
                'user_id' => $this->id,
                'service_id' => $serviceId,
                'status' => 'active'
            ],
            [
                'amount' => \DB::raw('amount + ' . $amount),
                'system' => 'currency' // TODO: Get from enum
            ]
        );
        BalanceUser::updateOrCreate([
            'user_id' => Auth::id(),
        ], [
            'balance' => \DB::raw('balance + ' . $amount),
            'currency' => 'SAR'
        ]);
        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'transaction_type' => $transaction_type,
            'amount' => $amount,
            'status' => 'active',
            'description' => $description
        ]);

        return true;
    }

    public function getSecretKeyAttribute($value)
    {
        try {
            // dd(Crypt::decrypt($value));
            return Crypt::decrypt($value);
        } catch (DecryptException $e) {
            $encrypt = new EncryptionHelper();
            return $encrypt->decrypt($value);
        }
    }



    public function getExpiredDate($pointsCnt)
    {
        $expiredDate = Carbon::now()->addYear()->format('Y-m-d');
        $temp_sms_price = 0;
        $tempPoint = 0;

        // Fetch balance logs using Eloquent
        $balanceLogs = $this->balanceLogs()
            ->where('proccess_balance_expire_date', 0)
            ->where('points_cnt', '>', 0)
            ->where('balance_expire_date', '>', now())
            ->orderBy('balance_expire_date')
            ->orderBy('id')
            ->get();
        $stillPoint = $pointsCnt;
        if (!empty($balanceLogs)) {

            foreach ($balanceLogs as $balanceLog) {
                $avaliableLogPoint = $balanceLog['points_cnt'] - $balanceLog['points_spent'];
                // @todo Undefined Function getNetAmount
                $sms_price = getNetAmount($balanceLog['amount']) / $balanceLog['points_cnt'];
            }
            // foreach ($balanceLogs as $balanceLog) {

            //     $expiredDate = $balanceLog->balance_expire_date ? Carbon::parse($balanceLog->balance_expire_date)->format('Y-m-d')
            //         : '';
            //     $tempPoint += $balanceLog->points_cnt - $balanceLog->points_spent;

            //     if ($pointsCnt <= $tempPoint) {
            //         break;
            //     }
            // }
        }
        return $expiredDate;
    }

    public function balanceLogs()
    {
        return $this->hasMany(BalanceLog::class, 'user_id');
    }

    /**
     * Get the business manager accounts for the user.
     *
     * @return HasMany
     */
    public function businessManagerAccounts(): HasMany
    {
        return $this->hasMany(BusinessManagerAccount::class, 'user_id');
    }

   public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }



    // Function to update last login date, IP, and agent

    public function getSmsPrice()
    {
        return .15; //TODO: remove from user
    }

    public function UpdateFailedCountLogin($offset_time)
    {
        // Calculate the new suspended time by subtracting the offset_time from the current time
        $login_time = now()->subMinutes($offset_time)->format('Y-m-d H:i');

        // Update the suspended_at and failed_count_login for the current record
        $this->update([
            'suspended_at' => $login_time,
            'failed_count_login' => 0
        ]);
    }

    public function updateLastLoginDateIpAgent($offset_time, $ip, $agent)
    {
        // Calculate login time based on offset
        $login_time = now()->subMinutes($offset_time)->format("Y-m-d H:i");

        // Update the user with new login information
        $this->update([
            'last_login_date' => $login_time,
            'last_login_ip' => $ip,
            'last_agent' => $agent,
        ]);
    }

    public function roles()
    {
        return $this->belongsToMany(
            IAMRole::class,
            'iam_role_user',
            'user_id',
            'iam_role_id'
        );
    }

    public function canAccess($menuId)
    {
        // Check if the user has individual permission or role permission
        return $this->hasPermission($menuId) || $this->hasRolePermission($menuId);
    }

    public function hasPermission($menuId)
    {
        // Check if user has specific menu permission
        return $this->permissions()->where('menu_id', $menuId)->where('can_access', true)->exists();
    }

    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    // Define the many-to-many relationship with IAM Roles

    public function hasRolePermission($menuId)
    {
        // Check if any of the user’s roles have access to a menu
        foreach ($this->roles as $role) {
            if ($role->permissions()->where('menu_id', $menuId)->where('can_access', true)->exists()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Organizations the user owns (one-to-many).
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * Define the many-to-many relationship with organizations via the OrganizationUser pivot table.
     *
     * @return BelongsToMany
     */
    public function organizationMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot(['status', 'invite_token', 'created_at', 'updated_at']);
    }

    public function canAccessURI($requestUri, $method): bool
    {
        $uriParts = explode('/', $requestUri);
        $organizationIndex = array_search('organizations', $uriParts);
        // If URI contains organization, check if user is owner
        if ($organizationIndex !== false && isset($uriParts[$organizationIndex + 1])) {
            $organizationId = $uriParts[$organizationIndex + 1];

            // Check if user is organization owner
            if (
                $this->ownedOrganizations()
                ->where('id', $organizationId)
                ->exists()
            ) {
                return true;
            }
        }

        $method = $method == "GET" || $method == "HEAD" ? "GET|HEAD" : $method;
        return $this // User has many roles
            ->whereHas('IAMRoles.policies.allowedDefinitions.resource', function ($query) use ($requestUri, $method) {
                $query->select('id') // Only retrieve the ID
                    ->where('method', $method)
                    ->where('uri', $requestUri);
            })
            ->exists();
    }



    // Check if the user has access to the given URI and method

    public function IAMRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            IAMRole::class,
            'iam_role_user',
            'user_id',
            'iam_role_id'
        )->withPivot(['organization_id', 'deleted_at'])
         ->wherePivotNull('deleted_at')
         ->using(IAMRoleUser::class);
    }



    public function hasChannelAccess($channelId)
    {
        return $this->where(function ($query) use ($channelId) {
            // Check owned organizations and their workspaces
            $query->whereHas('ownedOrganizations.workspaces.channels', function ($subQuery) use ($channelId) {
                $subQuery->where('channels.id', $channelId);
            });

            // Check organization memberships and their workspaces
            $query->orWhereHas('organizationMemberships.workspaces.channels', function ($subQuery) use ($channelId) {
                $subQuery->where('channels.id', $channelId);
            });
        })->exists();
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(
            Workspace::class,
            'workspace_users',
            'user_id',
            'workspace_id'
        );
    }

    public function routeNotificationForSms($notification)
    {
        return $this->number;
    }
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            //'total_balance'=>'double'
        ];
    }

    // Check if the user is an Inbox Agent
    public function isInboxAgent(): bool
    {
        return $this->IAMRoles()->where('name', IAMRole::INBOX_AGENT_ROLE)->orWhere('name', '=', 'Organization Manager')->exists();
    }

    public function isOrganizationOwner(Organization $organization): bool
    {
        return ($this->IAMRoles()->where('name', '=', IAMRole::ORGANIZATION_OWNER)->exists()) &&  ($organization->owner_id == $this->id);
    }

    /**
     * Get the inbox agent availability status.
     *
     * @return HasOne
     */
    public function inboxAgentAvailability(): hasOne
    {
        return $this->hasOne(InboxAgentAvailability::class, 'inbox_agent_id');
    }

    /**
     * Get the inbox agent availability status.
     *
     * @return HasMany
     */
    public function inboxAgentWorkingHours(): HasMany
    {
        return $this->hasMany(InboxAgentWorkingHour::class, 'inbox_agent_id');
    }


    public function currentWorkspace()
    {
        // If the user has a workspace_id set directly, use that
        if ($this->workspace_id) {
            return Workspace::find($this->workspace_id);
        }

        // Check workspaces through owned organizations
        $workspaceFromOwnedOrg = $this->ownedOrganizations()
            ->with('workspaces')
            ->first()?->workspaces()
            ->first();

        if ($workspaceFromOwnedOrg) {
            return $workspaceFromOwnedOrg;
        }

        // Check workspaces through organization memberships
        $workspaceFromMembership = $this->organizationMemberships()
            ->with('workspaces')
            ->first()?->workspaces()
            ->first();

        if ($workspaceFromMembership) {
            return $workspaceFromMembership;
        }

        // Finally, check if user has direct workspace associations
        return $this->workspaces()->first();
    }


    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }


    public function isInboxAgentBillingActive(): bool
    {
        // Check if user has active Inbox Agent billing via iam_role_user pivot
        // Also check soft-deleted records to avoid double charging
        $inboxAgentRoleId = IAMRole::where('name', IAMRole::INBOX_AGENT_ROLE)->value('id');

        return IAMRoleUser::where('user_id', $this->id)
            ->where('iam_role_id', $inboxAgentRoleId)
            ->where('is_billing_active', true)
            ->where('billing_cycle_end', '>=', now())
            ->exists();
    }

    protected $appends = ['is_inbox_agent_billing_active'];

    public function getIsInboxAgentBillingActiveAttribute(): bool
    {
        return $this->isInboxAgentBillingActive();
    }

    public function isMemberOfWorkspace($workspace): bool
    {
        return $this->workspaces()?->where('workspaces.id', $workspace->id)->exists();
    }

    public function IAMRolesForOrg(string $organizationId): BelongsToMany
    {
        return $this->IAMRoles()->wherePivot('organization_id', $organizationId);
    }
}
