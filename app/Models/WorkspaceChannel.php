<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceChannel extends Pivot
{
    protected $table = 'workspace_channel'; // Specify the pivot table name

    protected $fillable = [
        'workspace_id',
        'channel_id',
    ];

    // Optional: Add relationships if needed
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
