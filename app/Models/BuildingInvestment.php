<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BuildingInvestment extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'growth_in_market_value',
        'annual_expense',
        'mount_income',
    ];

    protected $casts = [
        'growth_in_market_value' => 'decimal:2',
        'annual_expense' => 'decimal:2',
        'mount_income' => 'decimal:2'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

}