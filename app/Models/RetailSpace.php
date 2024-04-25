<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class RetailSpace extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $fillable = [
        'area', 'type', 'property_id', 'description'
    ];

    protected $appends = ['images'];

    protected $hidden = ['media'];

    public function getImagesAttribute()
    {
        return $this->getMedia('retail_space')->map(fn (Media $media) => $media->getUrl());
    }


    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('retail_space');
    }

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }
}