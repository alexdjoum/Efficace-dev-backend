<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoPartBuilding extends Model
{
    use HasFactory;

    protected $table = 'photos_parts_building';

    protected $fillable = [
        'path_part_building',
        'part_of_building_id',
    ];

    public function partOfBuilding()
    {
        return $this->belongsTo(PartOfBuilding::class);
    }
}