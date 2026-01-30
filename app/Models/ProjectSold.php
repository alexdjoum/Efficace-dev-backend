<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectSold extends Model
{
    protected $fillable = [
        'project_id',
        'amount',
        'amount_received',
        'customer_of_name',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_received' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}