<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'uuid',
        'accepted',
        'status',
        'user_id',
        'description'
    ];

    protected $casts = [
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

    public function intentionToSell()
    {
        return $this->hasOne(IntentionToSellProject::class);
    }

    public function projectSales()
    {
        return $this->hasMany(ProjectSale::class);
    }

    public function currentSale()
    {
        return $this->hasOne(ProjectSale::class)->latest();
    }

}