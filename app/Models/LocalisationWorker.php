<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalisationWorker extends Model
{
    protected $fillable = ['name'];

    public function users()
    {
        return $this->hasMany(User::class, 'localisation_worker_id');
    }
}