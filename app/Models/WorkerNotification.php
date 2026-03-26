<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerNotification extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'status',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}