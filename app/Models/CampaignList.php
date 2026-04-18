<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CampaignList extends Model
{
    use HasFactory;

    protected $table = 'campaign_list';

    protected $fillable = [
        'campaign_id',
        'list_id',
    ];
    
    public function lists()
    {
        return $this->belongsTo(IAMList::class, 'list_id');
    }
}
