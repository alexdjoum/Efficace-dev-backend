<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Fragment extends Model
{
    use HasFactory;

    protected $fillable = [
        'area', 'land_id'
    ];

    public function land()
    {
        return $this->belongsTo(Land::class);
    }
}
