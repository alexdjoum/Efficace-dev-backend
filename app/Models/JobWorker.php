<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobWorker extends Model
{
    protected $fillable = [
        'job_id',
        'user_id',
        'note',
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}