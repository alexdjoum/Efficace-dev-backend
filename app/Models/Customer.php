<?php

namespace App\Models;

use App\Models\User;
use App\Models\Address;
use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $fillable = ['first_name', 'phone', 'last_name', 'type'];

    protected $with = ['address', 'user'];

    public function address()
    {
        return $this->morphOne(Address::class, "addressable");
    }

    public function user()
    {
        return $this->morphOne(User::class, "userable");
    }
}