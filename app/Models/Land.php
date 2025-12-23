<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\ProposedSiteOrLandProposed;

class Land extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, CustomLogsActivity;

    protected $fillable = [
        'area',
        'is_fragmentable',
        'relief',
        'description',
        'land_title',
        'certificat_of_ownership',
        'technical_doc',
        'location_id'
    ];

    protected $hidden = ['media', 'created_at', 'updated_at'];

    protected $appends = ['images'];

    protected $with = ['location', 'fragments', 'videoLands'];

    public function getImagesAttribute()
    {
        return $this->getMedia('land')->map(fn (Media $media) => $media->getUrl());
    }

    public function fragments()
    {
        return $this->hasMany(Fragment::class);
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('land');
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }

    public function videoLands()
    {
        return $this->hasMany(VideoLand::class, 'land_id');
    }

    public function proposedSites()
    {
        return $this->morphMany(ProposedSiteOrLandProposed::class, 'proposable');
    }
    
}