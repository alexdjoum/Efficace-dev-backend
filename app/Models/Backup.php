<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

class Backup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'path', 'size', 'disk', 'date'];

    protected $appends = ['human_size'];

    public function getHumanSizeAttribute()
    {
        return Number::fileSize($this->size, 2);
    }
}