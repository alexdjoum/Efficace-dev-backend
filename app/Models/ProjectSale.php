<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSale extends Model
{
    protected $fillable = [
        'project_id',
        'status',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function intentionToSell()
    {
        return $this->hasOne(IntentionToSellProject::class);
    }
}