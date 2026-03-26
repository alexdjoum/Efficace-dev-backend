<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectEngin extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'task',
        'note',
        'start_at',
        'end_at',
        'is_accepted',
    ];

    protected $casts = [
        'note' => 'decimal:1',
        'start_at' => 'date',
        'end_at' => 'date',
        'is_accepted' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function notification()
    {
        return $this->hasOne(EnginNotification::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function engin()
    {
        return $this->hasOneThrough(
            Engin::class,
            User::class,
            'id', 
            'user_id', 
            'user_id', 
            'id' 
        );
    }
}