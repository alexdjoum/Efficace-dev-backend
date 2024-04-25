<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Accommodation extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $fillable = [
        'reference',
        'dining_room',
        'kitchen',
        'bath_room',
        'bedroom',
        'living_room',
        'description',
        'type',
        'property_id',
    ];

    protected $appends = [
        'images'
    ];

    protected $hidden = [
        'media'
    ];

    public function getImagesAttribute()
    {
        return $this->getMedia('accommodation')->map(function (Media $media) {
            return $media->getUrl();
        });
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('accommodation');
    }

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }
}