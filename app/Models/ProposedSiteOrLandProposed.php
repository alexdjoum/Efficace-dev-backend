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
        'proposable_id',
        'proposable_type',
    ];

    public function proposable()
    {
        return $this->morphTo();
    }
}
