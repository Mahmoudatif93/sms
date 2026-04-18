<?php

namespace App\Models;

use App\Enums\WalletTransactionStatus;
use Carbon\Carbon;
use DB;
use Eloquent;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use OSS\Core\OssException;
use OSS\OssClient;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 *
 *
 * @property string $id
 * @property string $name
 * @property string $status
 * @property string $type
 * @property string $commercial_registration_number
 * @property string $unified_number
 * @property string|null $status_reason
 * @property int $owner_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read User|null $owner
 * @method static Builder|Organization newModelQuery()
 * @method static Builder|Organization newQuery()
 * @method static Builder|Organization query()
 * @method static Builder|Organization whereCreatedAt($value)
 * @method static Builder|Organization whereId($value)
 * @method static Builder|Organization whereName($value)
 * @method static Builder|Organization whereOwnerId($value)
 * @method static Builder|Organization whereSlug($value)
 * @method static Builder|Organization whereStatus($value)
 * @method static Builder|Organization whereType($value)
 * @method static Builder|Organization whereStatusReason($value)
 * @method static Builder|Organization whereUpdatedAt($value)
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read OrganizationUser $pivot
 * @property-read Collection<int, User> $members
 * @property-read int|null $members_count
 * @property-read Collection<int, Workspace> $workspaces
 * @property-read int|null $workspaces_count
 * @property string|null $file_commercial_register
 * @property string|null $file_value_added_tax_certificate
 * @property-read Collection<int, OrganizationWhatsappRateLine> $customWhatsappRateLines
 * @property-read int|null $custom_whatsapp_rate_lines_count
 * @property-read Collection<int, OrganizationWhatsappExtra> $hostingPlans
 * @property-read int|null $hosting_plans_count
 * @property-read Collection<int, OrganizationMembershipPlan> $membershipPlans
 * @property-read int|null $membership_plans_count
 * @property-read OtherQuota|null $otherQuota
 * @property-read Collection<int, Plan> $plans
 * @property-read int|null $plans_count
 * @property-read SmsQuota|null $smsQuota
 * @property-read Collection<int, Tag> $tags
 * @property-read int|null $tags_count
 * @property-read Collection<int, WalletAssignment> $walletAssignments
 * @property-read int|null $wallet_assignments_count
 * @property-read Collection<int, Wallet> $wallets
 * @property-read int|null $wallets_count
 * @property-read Collection<int, OrganizationWhatsappExtra> $whatsappExtras
 * @property-read int|null $whatsapp_extras_count
 * @property-read Collection<int, OrganizationWhatsappRate> $whatsappRates
 * @property-read int|null $whatsapp_rates_count
 * @property-read OrganizationWhatsappSetting|null $whatsappSetting
 * @property bool $auto_translation_enabled
 * @property array|null $supported_languages
 * @method static Builder<static>|Organization whereCommercialRegistrationNumber($value)
 * @method static Builder<static>|Organization whereFileCommercialRegister($value)
 * @method static Builder<static>|Organization whereFileValueAddedTaxCertificate($value)
 * @method static Builder<static>|Organization whereUnifiedNumber($value)
 * @mixin Eloquent
 */
class Organization extends Model implements HasMedia
{

    use InteractsWithMedia;

    public $incrementing = false;
    /**
     * The primary key is a UUID.
     *
     * @var string
     */
    protected $primaryKey = 'id';
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'name',
        'status',
        'status_reason',
        'owner_id',
        'type',
        'commercial_registration_number',
        'unified_number',
        'auto_translation_enabled',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'status' => 'string',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
        'auto_translation_enabled' => 'boolean',
    ];

    /**
     * Relationship with the User model (Owner of the organization).
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'id');
    }

    /**
     * Example of a method to check if the organization is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the signed URL for the avatar media file for preview.
     *
     * @param int $expirationInSeconds The number of seconds the signed URL should be valid for.
     * @return string|null The signed URL for the avatar media or null if no media is found.
     */
    public function getAvatarUrl(int $expirationInSeconds = 864000): ?string
    {
        // Retrieve the avatar media from the media collection
        $media = $this->getFirstMedia('organization-avatar');  // 'organization-avatar' is the media collection name

        if (!$media) {
            return null;  // Return null if no avatar media is found
        }

        // Get the path of the media object in OSS
        $objectPath = $media->getPath(); // This retrieves the path in OSS

        try {
            // Initialize the OSS Client
            $ossClient = new OssClient(
                env('OSS_ACCESS_KEY_ID'),
                env('OSS_ACCESS_KEY_SECRET'),
                env('OSS_ENDPOINT')
            );

            $bucket = env('OSS_BUCKET');

            // Generate the signed URL with 'Content-Disposition: inline' for preview
            return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
                OssClient::OSS_HEADERS => [
                    'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
                ],
            ]);

        } catch (OssException $e) {
            // Handle the exception (log it, return null, etc.)
            return null;
        }
    }

    public function getFileCommercialRegister(int $expirationInSeconds = 864000)
    {
        $media = $this->getFirstMedia('organization-commercial-register');  // 'commercial-register' is the media collection name

        if (!$media) {
            return null;  // Return null if no media is found
        }

        $objectPath = $media->getPath(); // This retrieves the path in OSS

        try {
            $fileUploadService = app()->make('App\Services\FileUploadService');
            return $fileUploadService->getSignUrl($objectPath, $expirationInSeconds, 'inline');
        } catch (Exception $e) {
            // Handle the exception (log it, return null, etc.)
            return null;
        }
    }

    public function getFileValueAddedTaxCertificate(int $expirationInSeconds = 864000)
    {
        $media = $this->getFirstMedia('organization-value-added-tax-certificate');  // 'value-added-tax-certificate' is the media collection name

        if (!$media) {
            return null;  // Return null if no media is found
        }

        $objectPath = $media->getPath(); // This retrieves the path in OSS

        try {
            $fileUploadService = app()->make('App\Services\FileUploadService');
            return $fileUploadService->getSignUrl($objectPath, $expirationInSeconds, 'inline');
        } catch (Exception $e) {
            // Handle the exception (log it, return null, etc.)
            return null;
        }
    }

    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'wallettable');
    }

    public function smsQuota()
    {
        return $this->morphOne(SmsQuota::class, 'quotable');
    }

    public function getSmsPrice()
    {
        return .15;
    }

    public function otherQuota()
    {
        return $this->morphOne(OtherQuota::class, 'quotable');
    }

    public function primaryWallet(?int $serviceId = null)
    {
        $query = $this->walletAssignments()
            ->where('assignment_type', 'primary')
            ->whereHas('wallet', function ($query) use ($serviceId) {
                if ($serviceId) {
                    $query->where('service_id', $serviceId);
                }
            })
            ->with([
                'wallet' => function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                }
            ]);
        $assignment = $query->first();
        return $assignment?->wallet;
    }

    public function walletAssignments()
    {
        return $this->morphMany(WalletAssignment::class, 'assignable');
    }

    /**
     * Connect organization to all existing plans
     */
    public function connectToAllPlans(): void
    {
        $planIds = Plan::whereNull('dealer_id')->pluck('id')->toArray();
        $this->plans()->sync($planIds);
    }

    /**
     * The plans that belong to the organization.
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)
            ->withPivot(['id', 'points_cnt', 'price', 'currency', 'is_custom', 'is_active'])
            ->withTimestamps();
    }

    public function allTransactions()
    {
        return WalletTransaction::whereHas('wallet', function ($query) {
            $query->where(function ($q) {
                $q->where('wallettable_type', Organization::class)
                    ->where('wallettable_id', $this->id)
                    ->orWhereIn('wallettable_id', $this->workspaces()->pluck('id'))
                    ->where('wallettable_type', Workspace::class)
                    ->orWhereIn('wallettable_id', $this->members()
                        ->wherePivotNull('organization_user.deleted_at')->pluck('organization_user.id'))
                    ->where('wallettable_type', OrganizationUser::class);
            });
        })->with('wallet')
            ->latest();
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    /**
     * Define the many-to-many relationship with users via OrganizationUser pivot table.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->using(OrganizationUser::class)
            ->withPivot(['status', 'invite_token'])
            ->withTimestamps();
    }

    public function whatsappRates(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappRate::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(OrganizationMembershipPlan::class, 'organization_id');
    }

    public function whatsappExtras(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappExtra::class, 'organization_id');
    }

    public function hostingPlans(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappExtra::class, 'organization_id');
    }

    public function senders(): HasMany
    {
        return $this->hasMany(Sender::class, 'organization_id');
    }

    /**
     * The tags that belong to the organization.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'organization_tags')
            ->withTimestamps();
    }

    public function whatsappSetting(): HasOne
    {
        return $this->hasOne(OrganizationWhatsappSetting::class);
    }

    public function usesCustomWhatsappRates(): bool
    {
        return $this->whatsappSetting?->use_custom_rates ?? false;
    }

    public function customWhatsappRateLines(): HasMany
    {
        return $this->hasMany(OrganizationWhatsappRateLine::class);
    }

    public function requiredActions(): HasMany
    {
        return $this->hasMany(RequiredAction::class, 'organization_id');
    }

    /**
     * Get the organization this notification belongs to.
     */
    public function dashboardNotifications(): HasMany
    {
        return $this->hasMany(DashboardNotification::class, 'organization_id');
    }

    public function whatsappRateLines(): WhatsappRateLine|Builder|\Illuminate\Database\Query\Builder
    {
        return WhatsappRateLine::query()
            ->leftJoin('organization_whatsapp_rate_lines as owr', function ($join) {
                $join->on('owr.whatsapp_rate_line_id', '=', 'whatsapp_rate_lines.id')
                    ->where('owr.organization_id', $this->id);
            })
            ->join('world_countries as countries', 'whatsapp_rate_lines.world_country_id', '=', 'countries.id')
            ->select([
                'whatsapp_rate_lines.id as rate_line_id',
                'countries.id as country_id',
                'countries.name_en as country_name',
                'countries.emoji as country_emoji',
                'whatsapp_rate_lines.category',
                'whatsapp_rate_lines.pricing_model',
                'whatsapp_rate_lines.effective_date',
                'whatsapp_rate_lines.expiry_date',
                DB::raw('COALESCE(owr.custom_price, whatsapp_rate_lines.price) as price'),
                DB::raw('COALESCE(owr.currency, whatsapp_rate_lines.currency) as currency'),
                DB::raw('CASE WHEN owr.id IS NOT NULL THEN true ELSE false END as is_custom'),
            ]);
    }

    /**
     * Get the inbox agent settings or create them with defaults.
     *
     * @return OrganizationInboxAgentSetting
     */
    public function getOrCreateInboxAgentSettings(): OrganizationInboxAgentSetting
    {
        return $this->inboxAgentSettings()->firstOrCreate([
            'organization_id' => $this->id,
        ]);
    }

    public function inboxAgentSettings(): HasOne
    {
        return $this->hasOne(OrganizationInboxAgentSetting::class);
    }

    public function createDefaultMembershipPlan(): void
    {
        // Prevent duplicates
        if ($this->membershipPlans()->exists()) {
            return;
        }

        $this->membershipPlans()->create([
            'service_id' => Service::where('name', \App\Enums\Service::OTHER)->value('id'),
            'currency' => 'SAR',
            'price' => 1800,
            'frequency' => 'monthly',
            'status' => 'inactive',
            'start_date' => Carbon::now(),
            'end_date' => null,
        ]);
    }

    public function createDefaultWhatsappSetting(): void
    {
        // Prevent duplicate setting
        if ($this->whatsappSetting()->exists()) {
            return;
        }

        $this->whatsappSetting()->create([
            'use_custom_rates' => true,
            'who_pays_meta' => 'client',
            'wallet_charge_mode' => 'markup_only',
            'markup_percentage' => 20,
        ]);
    }

    public function createDefaultWhatsappExtra(): void
    {
        if ($this->whatsappExtras()->exists()) {
            return;
        }

        $this->whatsappExtras()->create([
            'translation_quota' => 0.25,     // Translation fee per message (SAR)
            'chatbot_quota' => 37.5,         // Chatbot fee per month (10 USD approx.)
            'hosting_quota' => 750.0,        // Hosting fee per month (SAR)
            'inbox_agent_quota' => 180.0,    // Inbox agent fee per month (SAR)
            'frequency' => 'monthly',
            'free_tier' => false,
            'free_tier_limit' => 0,
            'is_active' => true,
            'effective_date' => now(),
            'expiry_date' => null,
        ]);
    }

    public function isMembershipBillingActive($membershipPlan): bool
    {
        return WalletTransaction::where('meta->type', 'membership_plan')
            ->where('meta->organization_id', $this->id)
            ->where('meta->membership_plan_id', $membershipPlan->id)
            ->where('meta->billing_cycle_end', '>=', now()->timestamp)
            ->where('status', WalletTransactionStatus::ACTIVE)
            ->exists();
    }

    /**
     * Convenience accessor with a safe default (0).
     * Usage: $organization->translation_quota
     */
    public function getTranslationQuotaAttribute(): float
    {
        return (float) ($this->whatsappExtra()->first()?->translation_quota ?? 0);
    }

    /**
     * Optional: helper to check if translations are billable.
     */
    public function hasTranslationQuota(): bool
    {
        return $this->translation_quota > 0;
    }

    public function getMainOtherWallet()
    {
        $serviceID = Service::firstOrCreate(
            ['name' => \App\Enums\Service::OTHER],
            ['description' => 'whatsapp,hlr']
        )->id;

        return $this->wallets()
            ->where('type', '=' , 'primary')
            ->where('service_id', '=', $serviceID)
            ->first();
    }

    /**
     * Check if auto translation is enabled for this organization.
     *
     * @return bool
     */
    public function isAutoTranslationEnabled(): bool
    {
        return $this->auto_translation_enabled ?? false;
    }

}
