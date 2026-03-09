<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    protected $fillable = [
        'lot_id',
        'user_id',
        'presentation',
        'is_enterprise',
        'years_of_experience',
        'account_creation_request',
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

    public function accountTypeLots()
    {
        return $this->hasMany(AccountTypeLot::class);
    }

    public function lots()
    {
        return $this->belongsToMany(Lot::class, 'account_type_lots')
            ->withTimestamps();
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }
}