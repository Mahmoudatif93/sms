<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\RequiredAction
 *
 * @property int $id
 * @property string $action_type
 * @property string $actionable_type
 * @property string $actionable_id
 * @property array|null $metadata
 * @property Carbon|null $due_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $dismissed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @property-read Model $actionable
 * @property-read string $status
 *
 * @method static Builder|RequiredAction pending()
 * @method static Builder|RequiredAction completed()
 * @method static Builder|RequiredAction dismissed()
 *
 * @mixin Eloquent
 */
class RequiredAction extends Model
{
    protected $fillable = [
        'organization_id',
        'action_type',
        'actionable_type',
        'actionable_id',
        'metadata',
        'due_at',
        'completed_at',
        'dismissed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'due_at' => 'timestamp',
        'completed_at' => 'timestamp',
        'dismissed_at' => 'timestamp',
    ];

    public function actionable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'actionable_type', 'actionable_id')
            ->morphWith([
                'channel' => Channel::class,
                'organization' => Organization::class,
                'user' => User::class,
                // add others as needed
            ]);
    }

    /**
     * Computed status: pending, completed, or dismissed.
     */
    public function getStatusAttribute(): string
    {
        if ($this->completed_at) {
            return 'completed';
        }

        if ($this->dismissed_at) {
            return 'dismissed';
        }

        return 'pending';
    }

    // ───────────────────────────────
    // 🔍 Query Scopes
    // ───────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at')->whereNull('dismissed_at');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeDismissed(Builder $query): Builder
    {
        return $query->whereNotNull('dismissed_at');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
