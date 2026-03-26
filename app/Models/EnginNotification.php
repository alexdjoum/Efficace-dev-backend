<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnginNotification extends Model
{
    protected $fillable = [
        'project_engin_id',
        'user_id',
        'status',
        'message',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function projectEngin()
    {
        return $this->belongsTo(ProjectEngin::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}