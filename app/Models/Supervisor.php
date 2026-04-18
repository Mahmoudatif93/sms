<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supervisor extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable,LogsActivity, SoftDeletes;


    protected $table = 'supervisor';
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'username',
        'password',
        'date',
        'email',
        'number',
        'lang',
        'status',
        'secret_key',
        'otp',
        'password_expiration_at',
    ];

    protected $hidden = [
        'password',
        'secret_key', // Hide sensitive fields
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the identifier that will be stored in the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }


    public static function getByUsername($username)
    {
        // Query the database to find the user by username
        $user = self::where('username', $username)->first();

        // If the user exists, return the user object; otherwise, return null
        return $user ?: null;
    }


//////////////////////////////logs////////////////
    protected static $logName = 'supervisor';
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'group_id',
                'username',
                'email',
                'number',
                'status',
            ])
            ->logOnlyDirty()
            ->useLogName('supervisor');
    }
    protected static $logAttributes = [
        'group_id',
        'username',
        'email',
        'number',
        'status',
    ];
    protected static $logOnlyDirty = true;

    public function getDescriptionForEvent(string $eventName): string
    {
        return "Supervisor record has been {$eventName}";
    }
}
