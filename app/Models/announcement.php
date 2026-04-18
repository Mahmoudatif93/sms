<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class announcement extends Model
{
    use HasFactory;
    
    protected $table = 'announcement'; // Replace with your actual table name
    protected $fillable = ['name','title_en','title_ar','text_email',
    'text_sms','media','budget'];

    public static function get_by_name($name){
        return announcement::where('name',$name)->first();
    }

}
