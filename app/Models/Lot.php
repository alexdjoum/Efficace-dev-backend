<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lot extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function accountTypes()
    {
        return $this->hasMany(AccountType::class);
    }

    public function users()
    {
        return $this->hasManyThrough(User::class, AccountType::class);
    }
}