<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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

    protected $hidden = ['media'];

    protected $appends = ['images', 'technical_doc', 'certificat_of_ownership', 'land_title'];

    public function getImagesAttributes()
    {
        return $this->getMedia('land')->map(fn (Media $media) => $media->getUrl());
    }

    public function getTechnicalDocAttribute()
    {
        return $this->getFirstMediaUrl();
    }

    public function getLandTitleAttribute()
    {
        return $this->getFirstMediaUrl();
    }

    public function getCertificatOfOwnershipAttribute()
    {
        return $this->getFirstMediaUrl();
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('land');
        $this->addMediaCollection('technical_doc')->singleFile();
        $this->addMediaCollection('certificat_of_ownership')->singleFile();
        $this->addMediaCollection('land_title')->singleFile();
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }
}
