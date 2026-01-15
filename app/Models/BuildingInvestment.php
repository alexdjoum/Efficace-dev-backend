<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuildingInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_finance_id',
        'growth_in_market_value',
        'annual_expense',
    ];

    protected $casts = [
        'growth_in_market_value' => 'decimal:2',
        'annual_expense' => 'decimal:2',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function buildingFinance()
    {
        return $this->belongsTo(BuildingFinance::class);
    }

    // public function operatingRatio()
    // {
    //     return $this->belongsTo(OperatingRatioExcludingTax::class, 'operating_ratio_excluding_tax_id');
    // }
}