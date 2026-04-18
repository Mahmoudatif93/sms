<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    use HasFactory;

    protected $fillable = ['name_ar', 'name_en', 'route_name', 'parent_id', 'operations'];

    // Optionally, children relationship

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')->with('children');
    }


    // Optionally, parent relationship
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }



    public function permissions()
    {
        return $this->hasMany(Permission::class);
    }

    public static function getMenuWithParent($id)
    {
        return self::with('parent')->find($id);
    }
}
