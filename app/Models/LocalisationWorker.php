<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalisationWorker extends Model
{
    protected $fillable = ['name', 'engin_id'];

    public function users()
    {
        return $this->hasMany(User::class, 'localisation_worker_id');
    }

    public function engin()
    {
        return $this->belongsTo(Engin::class);
    }
}