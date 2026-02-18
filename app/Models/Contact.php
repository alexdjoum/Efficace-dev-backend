<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = [
        'user_id',
        'phoneNumber',
        'firstName',
        'lastName',
        'email',
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