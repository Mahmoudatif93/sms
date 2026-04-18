<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model {
    use HasFactory;

    protected $fillable = ['name', 'parent_id'];

    // Relationship to get child categories
    public function children() {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Relationship to get products in a category
    public function products() {
        return $this->hasMany(Product::class);
    }
}

