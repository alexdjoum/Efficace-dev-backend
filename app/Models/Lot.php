<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $fillable = [
        'name',
        'role',
        'main_id',
    ];

    public function accountTypes()
    {
        return $this->hasMany(AccountType::class);
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, AccountType::class);
    }


    public function parent()
    {
        return $this->belongsTo(Lot::class, 'main_id');
    }

    public function children()
    {
        return $this->hasMany(Lot::class, 'main_id');
    }

    public function scopeMain($query)
    {
        return $query->where('role', 'main');
    }

    public function scopeChild($query)
    {
        return $query->where('role', 'child');
    }
}