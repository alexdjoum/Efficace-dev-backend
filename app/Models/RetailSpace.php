<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class RetailSpace extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $fillable = [
        'area', 'type', 'property_id'
    ];


    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('retail_space');
    }
}
