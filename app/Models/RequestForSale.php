<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestForSale extends Model
{
    protected $fillable = [
        'user_id',
        'description',
        'type',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}