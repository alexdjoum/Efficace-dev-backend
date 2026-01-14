<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuildingFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'project_study',
        'building_permit',
        'structural_work',
        'finishing',
        'equipments',
        'total_excluding_field',
        'cost_of_land',
    ];

    protected $casts = [
        'project_study' => 'decimal:2',
        'building_permit' => 'decimal:2',
        'structural_work' => 'decimal:2',
        'finishing' => 'decimal:2',
        'equipments' => 'decimal:2',
        'total_excluding_field' => 'decimal:2',
        'cost_of_land' => 'decimal:2',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['total_building_finance'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function getTotalBuildingFinanceAttribute()
    {
        return (float) $this->project_study 
            + (float) $this->building_permit 
            + (float) $this->structural_work 
            + (float) $this->finishing 
            + (float) $this->equipments 
            + (float) $this->cost_of_land;
    }

    public function buildingInvestments()
    {
        return $this->hasMany(BuildingInvestment::class);
    }
}