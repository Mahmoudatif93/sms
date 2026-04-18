<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BoardField extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'board_tab_id', 'name', 'type', 'options', 'required', 'enabled'];

    protected $casts = [
        'options'  => 'array',
        'required' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::created(function ($field) {
            $tab = $field->tab()->first(); // Load the related tab

            if ($tab && strtolower($tab->name) === 'general') {
                $boardId = $tab->board_id;
                // You can add logic here to update Board-related entities
            }
        });
    }

    public function tab()
    {
        return $this->belongsTo(BoardTab::class, 'board_tab_id');
    }
}
