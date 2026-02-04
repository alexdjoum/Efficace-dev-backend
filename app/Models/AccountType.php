<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = [
        'user_id',
        'lot_id',
        'worker',
        'is_enterprise',
        'years_of_experience',
        'presentation',
    ];

    protected $casts = [
        'years_of_experience' => 'integer',
        'is_enterprise' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}