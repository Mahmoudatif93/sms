<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $table = 'setting'; // Replace with your actual table name


    public $incrementing = true; // Ensure 'id' is auto-incrementing
    protected $keyType = 'int'; // Set the key type as integer

    protected $fillable = [
        'id',
        'category_en',
        'category_ar',
        'name',
        'caption_en',
        'caption_ar',
        'desc_en',
        'desc_ar',
        'value',
        'type',

    ];
    public static function get_by_name($name){

        $cacheKey = 'settings_' . $name;
        return Cache::remember($cacheKey, 60*60*24*30*3, function() use ($name) { // 3 months
            return Setting::where('name', $name)->first()->value;
        });

    }

    public static function getValueByName($name)
    {
        $cacheKey = 'settings_' . $name;
        return Cache::remember($cacheKey, 60*60*24*30*3, function() use ($name) { // 3 months
            return static::where('name', $name)->value('value');
        });
    }

}
