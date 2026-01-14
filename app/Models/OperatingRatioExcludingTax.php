<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperatingRatioExcludingTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'type',
        'montant',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function buildingInvestments()
    {
        return $this->hasMany(BuildingInvestment::class, 'operating_ratio_excluding_tax_id');
    }
}