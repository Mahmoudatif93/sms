<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Str;

class AccessKey extends Model
{
    public $incrementing = false;

    // Indicate that the primary key is a UUID
    protected $table = 'access_keys';
    protected $keyType = 'string';

    // Specify the fillable attributes
    protected $fillable = [
        'id',
        'organization_id',
        'name',
        'type',
        'description',
        'suffix',
        'token',
        'last_used_at',
    ];

    // Date casting for timestamp fields
    protected $casts = [
        'last_used_at' => 'timestamp',
        'created_at' => 'timestamp',
        'updated_at' => 'timestamp',
    ];

    /**
     * Relationship with the Organization model.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Relationship with the IAMRole model through the pivot table.
     */
    public function roles()
    {
        return $this->belongsToMany(IAMRole::class, 'access_key_iam_role', 'access_key_id', 'iam_role_id')
            ->withPivot('type')
            ->withTimestamps();
    }


    public function canAccessURI($uri, $method): bool
    {
        return $this->roles()
            ->whereHas('policies', function ($query) use ($uri, $method) {
                $query->whereHas('definitions', function ($query) use ($uri, $method) {
                    $query->whereHas('resource', function ($query) use ($uri, $method) {
                        $query->where('uri', $uri)->where('method', '=', $method);
                    });
                });
            })
            ->exists();
    }


    /**
     * Generate a new secure token in the form: ak_<suffix>.<secret>
     * Returns: ['token' => ..., 'suffix' => ..., 'secret' => ...]
     */
    public static function generateFormattedToken(): array
    {
        $suffix = Str::random(6);       // short unique identifier
        $secret = Str::random(22);      // random high-entropy part
        $token = "ak_{$suffix}.{$secret}";

        return [
            'token' => $token,
            'suffix' => $suffix,
            'secret' => $secret,
        ];
    }
}
