<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Location extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $hidden = ['created_at', 'updated_at'];
    protected $appends = ['kml'];
    protected $fillable = [
        'coordinate_link',
    ];

    protected $with = [
        'address'
    ];

    public function address()
    {
        return $this->morphOne(Address::class, 'addressable');
    }

    /**
     * Collection KML
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('kml');
    }

    public function getKmlAttribute()
    {
        $media = $this->getFirstMedia('kml');
        return $media ? $media->getUrl() : null;
    }
}
