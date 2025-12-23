<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoLand extends Model
{
    protected $fillable = ['idLand', 'video_link'];
    protected $hidden = ['created_at', 'updated_at'];

    public function land()
    {
        return $this->belongsTo(Land::class);
    }

     public function getIdLandAttribute()
    {
        return $this->attributes['land_id'];
    }
    
    public function getVideoLinkAttribute()
    {
        return $this->attributes['video_link'];
    }
}