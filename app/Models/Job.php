<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $fillable = [
        'user_id',
        'localisation_worker_id',
        'name',
        'description',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function localisationWorker()
    {
        return $this->belongsTo(LocalisationWorker::class, 'localisation_worker_id');
    }

    public function jobWorkers()
    {
        return $this->hasMany(JobWorker::class);
    }

    public function workers()
    {
        return $this->belongsToMany(User::class, 'job_workers')
            ->withPivot('note')
            ->withTimestamps();
    }
}