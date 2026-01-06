<?php

namespace App\Models;

use App\Models\Accommodation;
use Spatie\MediaLibrary\HasMedia;
use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\ProposedSiteOrLandProposed;

class Property extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;
    const TYPE_VILLA = 'villa';
    const TYPE_BUILDING = 'building';

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
        'bedrooms',           
        'bathrooms', 
        'number_of_salons',         
        'estimated_payment',  
    ];

    protected $hidden = ['media', 'location_id'];

    protected $appends = ['images'];

    // protected $with = ['accommodations', 'location', 'retail_spaces', 'proposedSites'];
    protected $with = [];

    public static function getTypes()
    {
        return [
            self::TYPE_VILLA,
            self::TYPE_BUILDING,
        ];
    }

    public static function isValidType($type)
    {
        return in_array($type, self::getTypes());
    }

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

    public function retail_spaces()
    {
        return $this->hasMany(RetailSpace::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }


    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('property');
    }

    public function product()
    {
        return $this->morphOne(Product::class, 'productable');
    }

    public function partOfBuildings()
    {
        return $this->hasMany(PartOfBuilding::class);
    }

    public function proposedSites()
    {
        return $this->hasMany(ProposedSiteOrLandProposed::class, 'property_id');
    }

    public function appointments()
    {
        return $this->morphMany(Appointment::class, 'appointable');
    }
}