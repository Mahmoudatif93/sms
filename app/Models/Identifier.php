<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 *
 *
 * @property int $id
 * @property string $contact_id
 * @property string $key
 * @property string $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Contact $contact
 * @method static Builder|Identifier newModelQuery()
 * @method static Builder|Identifier newQuery()
 * @method static Builder|Identifier query()
 * @method static Builder|Identifier whereContactId($value)
 * @method static Builder|Identifier whereCreatedAt($value)
 * @method static Builder|Identifier whereId($value)
 * @method static Builder|Identifier whereKey($value)
 * @method static Builder|Identifier whereUpdatedAt($value)
 * @method static Builder|Identifier whereValue($value)
 * @mixin Eloquent
 */
class Identifier extends Model
{
    // Specify the table if it's not the default 'identifiers'
    const PHONE_NUMBER_KEY = 'phone-number';
    const EMAIL_KEY = 'email-address';
    const FINGERPRINT_KEY = 'fingerprint';
    protected $table = 'identifiers';

    // Indicate which fields are mass assignable
    protected $fillable = ['contact_id', 'key', 'value'];


    /**
     * Define the relationship between Identifier and Contact.
     * Each identifier belongs to a contact.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class,'contact_id');
    }

    public static function existsFor(string $key, string $value, ?string $exceptContactId = null): bool
    {
        return self::where('key', $key)
            ->where('value', $value)
            ->when($exceptContactId, function ($query) use ($exceptContactId) {
                $query->where('contact_id', '!=', $exceptContactId);
            })
            ->exists();
    }

    public static function existsForOrg(string $organizationId, string $key, string $value, ?string $exceptContactId = null): bool
    {
        return static::query()
            ->where('key', $key)
            ->where('value', $value)
            ->whereHas('contact', fn ($q) => $q->where('organization_id', $organizationId))
            ->when($exceptContactId, fn ($q) => $q->where('contact_id', '!=', $exceptContactId))
            ->exists();
    }


    /**
     * Check if a (key, value) exists in a given workspace.
     * Optionally exclude a specific contact (for updates).
     */
    public static function existsInWorkspace(
        string $workspaceId,
        string $key,
        string $value,
        ?string $exceptContactId = null
    ): bool {
        return static::query()
            ->inWorkspace($workspaceId)
            ->where('key', $key)
            ->where('value', $value)
            ->when($exceptContactId, fn ($q) => $q->where('contact_id', '!=', $exceptContactId))
            ->exists();
    }


    /**
     * Scope: limit identifiers to a specific workspace (via the contact relation).
     */
    public function scopeInWorkspace(Builder $query, string $workspaceId): Builder
    {
        return $query->whereHas('contact', fn (Builder $q) =>
        $q->where('workspace_id', $workspaceId)
        );
    }

}
