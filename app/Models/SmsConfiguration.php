<?php

namespace App\Models;

use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
/**
 *
 *
 * @property int $id
 * @property string $connector_id
 * @property int $sender_id
 * @property string $status
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read Connector $connector
 * @mixin Eloquent
 */
class   SmsConfiguration extends Model
{

    use HasUuids;

    protected $table = 'sms_configurations';

    protected $fillable = [
        'id',
        'connector_id',
        'sender_id',
        'status',
    ];

    const STATUS_ACTIVE = "active";
    const STATUS_INACTIVE = "inactiive";
    const STATUS_PENDING = "pending";

    protected $casts = ['created_at' => 'timestamp', 'updated_at' => 'timestamp'];


    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }


    public function sender(): BelongsTo
    {
        return $this->belongsTo(Sender::class,'sender_id','id');

    }


}
