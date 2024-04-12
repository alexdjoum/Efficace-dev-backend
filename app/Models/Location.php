<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'address_id',
        'coordinate_link',
    ];

    protected $with = [
        'address'
    ];

    public function address()
    {
        return $this->belongsTo(Address::class);
    }
}
