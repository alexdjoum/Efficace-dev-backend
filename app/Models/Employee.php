<?php

namespace App\Models;

use App\Models\User;
use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Employee extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $with = ['user'];

    protected $fillable = ['first_name', 'last_name', 'phone', 'position'];

    public function user()
    {
        return $this->morphOne(User::class, 'userable');
    }
}