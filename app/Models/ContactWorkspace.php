<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * App\Models\ContactWorkspace
 *
 * Pivot model for the many-to-many relationship between contacts and workspaces.
 *
 * @property string $contact_id
 * @property string $workspace_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read ContactEntity $contact
 * @property-read Workspace $workspace
 *
 * @method static Builder|ContactWorkspace newModelQuery()
 * @method static Builder|ContactWorkspace newQuery()
 * @method static Builder|ContactWorkspace query()
 * @method static Builder|ContactWorkspace whereContactId($value)
 * @method static Builder|ContactWorkspace whereWorkspaceId($value)
 * @method static Builder|ContactWorkspace whereCreatedAt($value)
 * @method static Builder|ContactWorkspace whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
class ContactWorkspace extends Pivot
{
    protected $table = 'contact_workspace';

    protected $fillable = [
        'contact_id',
        'workspace_id',
    ];

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the contact associated with this pivot.
     */
    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

    /**
     * Get the workspace associated with this pivot.
     */
    public function workspace(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
