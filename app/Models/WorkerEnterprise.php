<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerEnterprise extends Model
{
    protected $fillable = [
        'enterprise_user_id',
        'worker_user_id',
        'status',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    public function enterprise()
    {
        return $this->belongsTo(User::class, 'enterprise_user_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_user_id');
    }
}