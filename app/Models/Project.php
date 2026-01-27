<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'accepted',
        'status',
        'user_id',
        'description'
    ];

    protected $casts = [
        'accepted' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectImages()
    {
        return $this->hasMany(ProjectImage::class);
    }

    public function projectFiles()
    {
        return $this->hasMany(ProjectFile::class);
    }

}