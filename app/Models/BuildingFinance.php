<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuildingFinance extends Model
{
    use HasFactory;
    const TYPE_HIGH = 'high';
    const TYPE_MEDIUM = 'medium';
    const TYPE_LOW = 'low';


    protected $fillable = [
        'property_id',
        'project_study',
        'building_permit',
        'structural_work',
        'finishing',
        'equipments',
        'cost_of_land',
        'type_of_standing',
    ];

    protected $casts = [
        'project_study' => 'decimal:2',
        'building_permit' => 'decimal:2',
        'structural_work' => 'decimal:2',
        'finishing' => 'decimal:2',
        'equipments' => 'decimal:2',
        'cost_of_land' => 'decimal:2',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    protected $appends = ['total_excluding_field', 'total_building_finance'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function getTotalExcludingFieldAttribute()
    {
        return round(
            (float) $this->project_study 
            + (float) $this->building_permit 
            + (float) $this->structural_work 
            + (float) $this->finishing 
            + (float) $this->equipments,
            2
        );
    }

    public function getTotalBuildingFinanceAttribute()
    {
        return round(
            (float) $this->cost_of_land 
            + (float) $this->total_excluding_field,
            2
        );
    }

    public function buildingInvestment() 
    {
        return $this->hasOne(BuildingInvestment::class);
    }

    public function getTranslatedStandingAttribute()
    {
        return __('attributes.' . $this->type_of_standing);
    }
}