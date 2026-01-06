<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class PartOfBuilding extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'title',
        'description',
        'property_id',
    ];

    protected $appends = ['photos'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function photos()
    {
        return $this->hasMany(PhotoPartBuilding::class);
    }

    public function getPhotosAttribute()
    {
        return $this->getMedia('part_photos')->map(function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media) {
            return $media->getUrl();
        });
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('part_photos');
    }
}