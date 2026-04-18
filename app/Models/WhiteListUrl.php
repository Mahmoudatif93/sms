<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhiteListUrl extends Model
{
    use HasFactory;

    protected $table = 'whitelist_url'; // Replace with your actual table name
    protected $fillable = [
        'url'
    ];

    /**
     * Check if a URL exists in the whitelist.
     *
     * @param string $url
     * @return bool
     */
    public static function existsByUrl($url) : bool
    { 
       return WhiteListUrl::where('url',$url)->count();
    }

}
