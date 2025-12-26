<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposedSiteOrLandProposed extends Model
{
    use HasFactory;

    protected $fillable = [
        'land_id',
        'proposable_id',
        'property_id',
        'proposable_type',
    ];

    public function proposable()
    {
        return $this->morphTo();
    }

    public function land()
    {
        return $this->belongsTo(Land::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
