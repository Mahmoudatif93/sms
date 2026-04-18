<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Deal;

class PipelineField extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'pipeline_tab_id', 'name', 'type', 'options', 'required', 'enabled', 'position'];

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
                $pipelineId = $tab->pipeline_id;
                $deals = Deal::where('pipeline_id', $pipelineId)->get();

                /*  foreach ($deals as $deal) {
                    $customFields = json_decode($deal->custom_fields, true) ?? [];

                    if (!array_key_exists($field->name, $customFields)) {
                        $customFields[$field->name] = null;
                    }

                    $deal->custom_fields = json_encode($customFields);
                    $deal->save();
                }*/
            }
        });
    }

    public function tab()
    {
        return $this->belongsTo(PipelineTab::class, 'pipeline_tab_id');
    }
}
