<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Contract extends Model implements HasMedia
{
    use HasFactory, CustomLogsActivity, InteractsWithMedia;

    protected $fillable = ['terms'];

    protected $appends = ['document_url'];

    public function getDocumentUrlAttribute()
    {
        return $this->getFirstMediaUrl('contract');
    }

    public function contractable()
    {
        return $this->morphTo();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('contract')->singleFile();
    }
}
