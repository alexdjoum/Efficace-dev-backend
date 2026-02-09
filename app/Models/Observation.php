<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Observation extends Model
{
    protected $fillable = [
        'project_id',
        'user_id',
        'name',
        'description',
        'critical',
        'document_type',
        'project_image_id',
        'project_file_id',
        'coordinates',
    ];

    protected $casts = [
        'coordinates' => 'array', 
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function projectImage()
    {
        return $this->belongsTo(ProjectImage::class);
    }

    public function projectFile()
    {
        return $this->belongsTo(ProjectFile::class);
    }
}