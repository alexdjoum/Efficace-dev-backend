<?php

namespace App\Models;

use Spatie\MediaLibrary\HasMedia;
use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Virtual extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $appends = ['images'];

    protected $hidden = ['media'];

    protected $with = ['property'];

    public function getImagesAttribute()
    {
        return $this->getMedia('virtual')->map(fn (Media $media) => $media->getUrl());
    }
    protected $fillable = [
        'property_id', 'archi_project_price', 'big_work_price', 'building_permit_price', 'finishing_price', 'land_price', 'total_project_price', 'description', 'delivery_delay'
    ];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('virtual');
    }

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }
}