<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMessage extends BaseMessage
{
    use HasFactory;

  protected $table = 'admin_message';
  const CREATED_AT = 'creation_datetime';
  const UPDATED_AT = 'updation_datetime';

  public function getNumbers($limit)
    {
        return AdminMessageDetails::getNumbers($this->id, $limit);
    }

    protected static function getDetailsModel()
    {
        return new AdminMessageDetails();
    }

    public function details()
{
    return $this->hasMany(AdminMessageDetails::class, 'message_id');
}
}
