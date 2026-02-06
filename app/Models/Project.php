<?php

namespace App\Models;

use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'uuid',
        'amount',
        'amount_to_perceive',
        'status',
        'accepted',
        'user_id',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_to_perceive' => 'decimal:2',
        'accepted' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($project) {
            if (empty($project->uuid)) {
                $project->uuid = self::generateUuid();
            }
        });
    }

    public static function generateUuid()
    {
        do {
            $code = strtoupper(Str::random(3));
            $uuid = "#{$code}#";
        } while (self::where('uuid', $uuid)->exists());

        return $uuid;
    }

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

    public function projectSolds()
    {
        return $this->hasMany(ProjectSold::class);
    }

    public function observations()
    {
        return $this->hasMany(Observation::class);
    }

}