<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 *
 *
 * @OA\Schema (
 *     schema="IAMList",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="workspace_id", type="integer"),
 *     @OA\Property(property="type", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp"),
 * ),
 * @property string $id
 * @property string $name
 * @property string $workspace_id
 * @property string $type
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Campaign> $campaigns
 * @property-read int|null $campaigns_count
 * @property-read Collection<int, ContactEntity> $contacts
 * @property-read int|null $contacts_count
 * @method static Builder|IAMList newModelQuery()
 * @method static Builder|IAMList newQuery()
 * @method static Builder|IAMList query()
 * @method static Builder|IAMList whereCreatedAt($value)
 * @method static Builder|IAMList whereDescription($value)
 * @method static Builder|IAMList whereId($value)
 * @method static Builder|IAMList whereName($value)
 * @method static Builder|IAMList whereType($value)
 * @method static Builder|IAMList whereUpdatedAt($value)
 * @method static Builder|IAMList whereWorkspaceId($value)
 * @mixin Eloquent
 */
class IAMList extends Model
{
    public $incrementing = false;
    protected $table = "lists";
    protected $keyType = 'string';
    protected $fillable = [
        'name',
        'organization_id',
        'type',
        'parent_id',
        'description',
        'status',
        'total_contacts',
        'processed_contacts',
        'error_message',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PENDING = 'pending';
    const STATUS_FAILED = 'failed';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            $model->id = (string) Str::uuid(); // Generate a UUID
        });
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(ContactEntity::class, 'contact_list', 'list_id', 'contact_id'); // list_id is now a UUID
    }

    // Many-to-Many relationship with Campaign through CampaignList
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'campaign_list', 'list_id', 'campaign_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(IAMList::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(IAMList::class, 'parent_id');
    }


    public function childrenWithContacts(): HasMany
    {
        return $this->hasMany(IAMList::class, 'parent_id')->whereHas('contacts');
    }

    public function allContacts(): BelongsToMany
    {
        return $this->belongsToMany(ContactEntity::class, 'contact_list', 'list_id', 'contact_id');
    }

    /**
     * Mark the list as pending.
     */
    public function markAsPending(int $totalContacts = 0): void
    {
        $this->update([
            'status' => self::STATUS_PENDING,
            'total_contacts' => $totalContacts,
            'processed_contacts' => 0,
            'error_message' => null,
        ]);
    }

    /**
     * Mark the list as active (completed).
     */
    public function markAsActive(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'processed_contacts' => $this->total_contacts,
        ]);
    }

    /**
     * Mark the list as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Update the progress of contact processing.
     */
    public function updateProgress(int $processedCount): void
    {
        $this->increment('processed_contacts', $processedCount);
        $this->refresh();

        if ($this->processed_contacts >= $this->total_contacts) {
            $this->markAsActive();
        }
    }

    /**
     * Check if the list is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the list is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the list failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

}
