<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Collection;

/**
 * App\Models\Workspace
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property array|null $data_policy
 * @property string $status
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read Organization $organization
 * @property-read Collection<int, Channel> $channels
 * @property-read int|null $channels_count
 * @property-read Collection<int, Conversation> $conversations Conversations from all channels in this workspace
 * @property-read int|null $conversations_count
 *
 * @method static Builder|Workspace newModelQuery()
 * @method static Builder|Workspace newQuery()
 * @method static Builder|Workspace query()
 * @method static Builder|Workspace whereCreatedAt($value)
 * @method static Builder|Workspace whereDataPolicy($value)
 * @method static Builder|Workspace whereDescription($value)
 * @method static Builder|Workspace whereId($value)
 * @method static Builder|Workspace whereName($value)
 * @method static Builder|Workspace whereOrganizationId($value)
 * @method static Builder|Workspace whereStatus($value)
 * @method static Builder|Workspace whereUpdatedAt($value)
 * @mixin Eloquent
 */
class Workspace extends Model
{
    use SoftDeletes, Notifiable;
    public $incrementing = false;
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'description',
        'status'
    ];

    protected $casts = [
        'id' => 'string',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];


    /**
     * A workspace belongs to an organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'workspace_channel')
            ->using(WorkspaceChannel::class) // Specify the custom pivot model
            ->withTimestamps(); // Include timestamps
    }


    public function lists(): HasMany
    {
        return $this->hasMany(IAMList::class);
    }

    public function wallets()
    {
        return $this->morphMany(Wallet::class, 'wallettable');
    }

    public function walletAssignments()
    {
        return $this->morphMany(WalletAssignment::class, 'assignable');
    }

    public function smsQuota()
    {
        return $this->morphOne(SmsQuota::class, 'quotable');
    }


    public function getWorkspaceWallet(?int $serviceId = null)
    {
        return $this->walletAssignments()
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
            ])
            ->first()?->wallet;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_users')
            ->using(WorkspaceUser::class)
            ->withPivot(['status']) //'notification_preferences'
            ->withTimestamps();
    }

    /* public function conversations()
    {
        return $this->hasMany(Conversation::class , 'workspace_id');
    }*/

    public function conversations()
    {  return $this->hasMany(Conversation::class , 'workspace_id');
    }


    public function tickets()
    {
        $channelIds = $this->channels()->pluck('channels.id');
        return TicketEntity::whereIn('channel_id', $channelIds);
    }

    public function requiredActions(): HasMany
    {
        return $this->hasMany(RequiredAction::class, 'workspace_id');
    }

    /**
     * Get the organization this notification belongs to.
     */
    public function dashboardNotifications(): HasMany
    {
        return $this->hasMany(DashboardNotification::class, 'workspace_id');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            ContactEntity::class,
            'contact_workspace',
            'workspace_id',  // FK on the pivot table pointing to Workspace
            'contact_id'     // FK on the pivot table pointing to ContactEntity
        )
            ->using(ContactWorkspace::class)
            ->withTimestamps();
    }
}
