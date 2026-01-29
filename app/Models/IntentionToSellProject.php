<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntentionToSellProject extends Model
{
    protected $fillable = [
        'project_id',
        'project_sale_id',
        'amount_project',
        'amount_to_be_collected',
        'is_sold',
    ];

    protected $casts = [
        'amount_project' => 'decimal:2',
        'amount_to_be_collected' => 'decimal:2',
        'is_sold' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projectSale()
    {
        return $this->belongsTo(ProjectSale::class);
    }
}