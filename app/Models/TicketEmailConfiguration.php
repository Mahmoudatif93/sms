<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Class TicketEmailConfiguration
 *
 * Represents email configuration for ticket creation via email.
 *
 * @package App\Models
 * @property string $id UUID Primary Key
 * @property string $workspace_id Foreign Key - The workspace this configuration belongs to
 * @property string $email_address The email address that will receive ticket emails
 * @property string $mail_server The mail server hostname
 * @property string $mail_port The mail server port
 * @property string $mail_username The username for authentication
 * @property string $mail_password The password for authentication (encrypted)
 * @property string $mail_encryption The encryption type (tls, ssl, etc.)
 * @property bool $is_active Whether this configuration is active
 * @property Carbon|null $created_at Timestamp when the configuration was created
 * @property Carbon|null $updated_at Timestamp when the configuration was last updated
 *
 * @property-read Workspace $workspace The workspace this configuration belongs to
 *
 * @method static Builder|TicketEmailConfiguration newModelQuery()
 * @method static Builder|TicketEmailConfiguration newQuery()
 * @method static Builder|TicketEmailConfiguration query()
 * @method static Builder|TicketEmailConfiguration whereId($value)
 * @method static Builder|TicketEmailConfiguration whereWorkspaceId($value)
 * @method static Builder|TicketEmailConfiguration whereEmailAddress($value)
 * @method static Builder|TicketEmailConfiguration whereIsActive($value)
 *
 * @mixin Eloquent
 */
class TicketEmailConfiguration extends Model
{
    use HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ticket_email_configurations';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'workspace_id',
        'email_address',
        'mail_server',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_encryption',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'mail_password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the workspace that this configuration belongs to.
     *
     * @return BelongsTo
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    /**
     * Scope a query to only include active email configurations.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Set the mail_password attribute with encryption.
     *
     * @param string $value
     * @return void
     */
    public function setMailPasswordAttribute(string $value): void
    {
        $this->attributes['mail_password'] = encrypt($value);
    }

    /**
     * Get the decrypted mail_password attribute.
     *
     * @return string
     */
    public function getDecryptedPasswordAttribute(): string
    {
        return decrypt($this->mail_password);
    }

    /**
     * Get configuration for email handling.
     *
     * @return array
     */
    public function getMailConfig(): array
    {
        return [
            'driver' => 'imap',
            'host' => $this->mail_server,
            'port' => $this->mail_port,
            'username' => $this->mail_username,
            'password' => $this->getDecryptedPasswordAttribute(),
            'encryption' => $this->mail_encryption,
            'validate_cert' => true,
        ];
    }
}