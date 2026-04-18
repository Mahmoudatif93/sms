<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;
    protected $table = 'ticket'; // Replace with your actual table name
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'organization_id',
        'title',
        'content',
        'date',
        'status',
        'update_date',

    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    // Accessor for status
    public function getStatusAttribute($value)
    {
        return $value == 0 ? 'open' : 'closed';
    }

    //return ticket_status
    public function getStatusTextAttribute()
    {

        if ($this->attributes['status'] == 0) {
            return "غير مقروء";
        } elseif ($this->attributes['status'] == 1) {
            return "تم الرد _ المشرف ";
        } elseif ($this->attributes['status'] == 2) {
            return "تمت القراءة _ العميل";
        } elseif ($this->attributes['status'] == 3) {
            return "تم الرد _ العميل";
        } elseif ($this->attributes['status'] == 4) {
            return "اغلاق من المشرف";
        } elseif ($this->attributes['status'] == 5) {
            return "msg_user_closed";
        } else {
            return '-';
        }
    }


    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * Get the contact associated with the conversation.
     *
     * @return BelongsTo
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(ContactEntity::class, 'contact_id');
    }

}
