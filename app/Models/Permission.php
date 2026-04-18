<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['menu_id', 'role_id', 'user_id', 'can_access'];

    public function menu()
    {
        return $this->belongsTo(Menu::class)->with('children');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
