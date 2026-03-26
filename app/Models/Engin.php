<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Engin extends Model
{
    protected $fillable = [
        'user_id',
        'nameOfTheEngin',
        'brandOfTheDevice',
        'feature',
        'localisation_worker_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function localisationWorker()
    {
        return $this->belongsTo(LocalisationWorker::class);
    }

}