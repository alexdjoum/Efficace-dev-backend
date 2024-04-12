<?php

namespace App\Models;

use App\Models\Accommodation;
use Spatie\MediaLibrary\HasMedia;
use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Property extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $fillable = [
        'title',
        'build_area',
        'field_area',
        'levels',
        'has_garden',
        'parkings',
        'has_pool',
        'basement_area',
        'ground_floor_area',
        'type',
        'description',
        'location_id',
    ];

    protected $hidden = ['media'];

    protected $appends = ['images'];

    public function getImagesAttribute()
    {
        return $this->getMedia('property')->map(function (Media $media) {
            return $media->getUrl();
        });
    }

    public function accommodations()
    {
        return $this->hasMany(Accommodation::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('property');
    }
}