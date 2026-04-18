<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\DashboardNotification
 *
 * @property int $id
 * @property string|null $title
 * @property string|null $message
 * @property string|null $link
 * @property string|null $icon
 * @property string|null $category
 * @property string|null $status
 * @property string|null $workspace_id
 * @property string|null $organization_id
 * @property string|null $notifiable_type
 * @property string|null $notifiable_id
 * @property int|null $read_at
 * @property int|null $created_at
 * @property int|null $updated_at
 *
 * @method static Builder|DashboardNotification newModelQuery()
 * @method static Builder|DashboardNotification newQuery()
 * @method static Builder|DashboardNotification query()
 * @method static Builder|DashboardNotification unread()
 * @method static Builder|DashboardNotification whereWorkspaceId($id)
 * @method static Builder|DashboardNotification whereOrganizationId($id)
 *
 * @mixin Eloquent
 */
class DashboardNotification extends Model
{


    protected $table = 'dashboard_notifications';

    protected $fillable = [
        'title',
        'message',
        'link',
        'icon',
        'category',
        'status',
        'workspace_id',
        'organization_id',
        'notifiable_type',
        'notifiable_id',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Scope a query to only include unread notifications.
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Get the organization this notification belongs to.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the workspace this notification belongs to.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Get the notifiable entity (e.g., RequiredAction).
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }
}
