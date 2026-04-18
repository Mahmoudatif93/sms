<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class WorkspaceUser extends Pivot
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MUTED = 'muted';
    public const STATUS_BLOCKED = 'blocked';

    protected $table = 'workspace_users';

    protected $fillable = [
        'user_id',
        'workspace_id',
        'status'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    public static function getFirstWorkspaceId($userId): ?string
    {
        return static::where('user_id', $userId)
            ->where('status', self::STATUS_ACTIVE)
            ->value('workspace_id');
    }

}
