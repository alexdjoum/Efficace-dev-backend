<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestForSale extends Model
{
    protected $fillable = [
        'user_id',
        'saleable_type',
        'saleable_id',
        'description',
        'type',
        'status',
        'has_validated_contrat',
    ];

    protected $casts = [
        'has_validated_contrat' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function saleable()
    {
        return $this->morphTo();
    }
}